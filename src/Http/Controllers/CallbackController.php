<?php

namespace KraftInk\Plugins\Vote\Http\Controllers;

use App\Kraft\Audit;
use App\Exceptions\ConfirmationRequired;
use App\Kraft\Auth\ApiTokens;
use App\Kraft\System\OperationLock;
use App\Kraft\System\SensitiveAction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use KraftInk\Plugins\Vote\Models\Voting\CallbackReceipt;
use KraftInk\Plugins\Vote\Models\Voting\Challenge;
use KraftInk\Plugins\Vote\Models\Voting\Site;
use KraftInk\Plugins\Vote\Services\Providers\CallbackProvider;
use KraftInk\Plugins\Vote\Services\Providers\ProviderRegistry;
use KraftInk\Plugins\Vote\Services\VoteService;
use Illuminate\Http\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Validator as V;
use Symfony\Component\HttpKernel\Exception\HttpException;

class CallbackController
{
    public function showUrl(Request $request, int $id): Response|RedirectResponse
    {
        app(SensitiveAction::class)->confirm($request);
        [$site, $provider] = $this->provider($id, false);
        if ($request->boolean('rotate')) {
            $request->validate(['confirm_rotation' => ['accepted']]);
            DB::transaction(function () use ($site) {
                Site::query()->where('id', $site->id)->lockForUpdate()->first();
                Site::query()
                    ->where('id', $site->id)
                    ->update([
                        'callback_secret' => Crypt::encryptString(bin2hex(random_bytes(32))),
                        'updated_at' => now(),
                    ]);
                Challenge::query()
                    ->where('site_id', $site->id)
                    ->whereNull('used_at')
                    ->whereNull('provider_claimed_at')
                    ->update(['expires_at' => now()]);
                Audit::record('vote.callback_secret.rotated', ['site_id' => $site->id]);
            }, 3);
            $site = Site::query()->find($site->id);
        }

        $secret = $provider->endpointSecret($site);
        if (ConfirmationRequired::applies($request) && $request->header('X-Kraft-Form') === 'true') {
            return redirect()
                ->route('plugins.vote.admin.sites.callback-url.result', $site->id)
                ->with('vote.callback.revealed', [
                    'actor_id' => $request->user()->id,
                    'actor_stamp' => app(ApiTokens::class)->stamp($request->user()),
                    'site_id' => $site->id,
                    'secret_hash' => hash('sha256', $secret),
                ])
                ->withHeaders(['Cache-Control' => 'private, no-store', 'Pragma' => 'no-cache']);
        }

        return $this->callbackResponse($site, $secret);
    }

    public function revealedUrl(Request $request, int $id): Response
    {
        $revealed = $request->session()->pull('vote.callback.revealed');
        if (
            $request->expectsJson() ||
            !is_array($revealed) ||
            ($revealed['actor_id'] ?? null) !== $request->user()->id ||
            ($revealed['site_id'] ?? null) !== $id ||
            !is_string($revealed['actor_stamp'] ?? null) ||
            !hash_equals($revealed['actor_stamp'], app(ApiTokens::class)->stamp($request->user())) ||
            !is_string($revealed['secret_hash'] ?? null)
        ) {
            abort(404);
        }
        [$site, $provider] = $this->provider($id, false);
        $secret = $provider->endpointSecret($site);
        if (!hash_equals($revealed['secret_hash'], hash('sha256', $secret))) {
            abort(404);
        }

        return $this->callbackResponse($site, $secret);
    }

    private function callbackResponse(Site $site, string $secret): Response
    {
        return response()
            ->view('vote::admin.sites.callback', [
                'site' => $site,
                'callbackUrl' => route('plugins.vote.webhooks.providers.handle', [
                    'id' => $site->id,
                    'secret' => $secret,
                ]),
            ])
            ->withHeaders(['Cache-Control' => 'private, no-store', 'Pragma' => 'no-cache']);
    }

    public function handle(Request $request, int $id, string $secret): JsonResponse|Response
    {
        if (strlen($request->getContent()) > 65536) {
            throw new HttpException(413);
        }
        [$site, $provider] = $this->provider($id);
        if (!hash_equals($provider->endpointSecret($site), $secret)) {
            throw new HttpException(401);
        }
        try {
            $entries = $this->entries($request, $provider, $provider->settings($site));
        } catch (ValidationException $exception) {
            return response()->json(['errors' => $exception->errors()], 422);
        }
        $lease = app(OperationLock::class)->acquireShared();
        try {
            foreach ($entries as $entry) {
                $this->record($site, $entry);
            }
        } finally {
            $lease->release();
        }

        return response($provider->id() === 'mineserv' ? 'done' : 'OK')->header('Cache-Control', 'private, no-store');
    }

    private function provider(int $id, bool $enabled = true): array
    {
        /** @var Site|null $site */
        $query = Site::query();
        $site = ($enabled ? $query->available() : $query->activeGame())->find($id);
        if (!($site && app(ProviderRegistry::class)->has($site->provider))) {
            abort(404);
        }
        $provider = app(ProviderRegistry::class)->get($site->provider);
        if (!($provider instanceof CallbackProvider)) {
            abort(404);
        }

        return [$site, $provider];
    }

    private function entries(Request $request, CallbackProvider $provider, array $configuration): array
    {
        if ($provider->id() === 'topg') {
            $data = $request->validate([
                'p_resp' => ['required', 'string', 'regex:/^[A-Za-z0-9]{64}$/D'],
                'ip' => ['required', 'ip'],
            ]);

            return [
                ['nonce' => $data['p_resp'], 'ip' => $data['ip'], 'event' => 'topg:'.hash('sha256', $data['p_resp'])],
            ];
        }
        if ($provider->id() === 'xtremetop100') {
            $data = $request->validate([
                'nick' => ['required', 'string', 'regex:/^[A-Za-z0-9]{64}$/D'],
                'votingip' => ['required', 'ip'],
            ]);

            return [
                [
                    'nonce' => $data['nick'],
                    'ip' => $data['votingip'],
                    'event' => 'xtremetop100:'.hash('sha256', $data['nick']),
                ],
            ];
        }
        if ($provider->id() === 'arena-top100') {
            $data = $request->validate([
                'secret' => ['required', 'string'],
                'voted' => ['required', 'in:1'],
                'reset' => ['required', 'integer'],
                'userip' => ['required', 'ip'],
                'userid' => ['required', 'string', 'regex:/^[A-Za-z0-9]{64}$/D'],
            ]);
            if (!(hash_equals($configuration['callback_key'], $data['secret']) && $data['secret'] !== 'TEST')) {
                throw new HttpException(401);
            }
            if (!((int) $data['reset'] > time() && (int) $data['reset'] <= time() + 86400)) {
                throw new HttpException(422);
            }

            return [
                [
                    'nonce' => $data['userid'],
                    'ip' => $data['userip'],
                    'event' => 'arena:'.hash('sha256', inet_pton($data['userip']).':'.$data['reset']),
                ],
            ];
        }
        if ($provider->id() === 'mineserv') {
            $data = $request->validate([
                'project' => ['required', 'integer', 'min:1'],
                'username' => ['required', 'string', 'regex:/^[A-Za-z0-9_]{1,32}$/D'],
                'timestamp' => ['required', 'integer'],
                'signature' => ['required', 'string', 'regex:/^[a-fA-F0-9]{64}$/D'],
            ]);
            if ((int) $data['project'] !== (int) $configuration['server_id']) {
                throw new HttpException(422);
            }
            $signature = hash(
                'sha256',
                $data['project'].
                    '.'.
                    $configuration['callback_key'].
                    '.'.
                    $data['timestamp'].
                    '.'.
                    $data['username'],
            );
            if (!hash_equals($signature, strtolower($data['signature']))) {
                throw new HttpException(401);
            }
            if (!(abs(time() - (int) $data['timestamp']) <= 300)) {
                throw new HttpException(422);
            }

            return [
                [
                    'player' => $data['username'],
                    'time' => (int) $data['timestamp'],
                    'event' => 'mineserv:'.hash('sha256', $signature),
                ],
            ];
        }
        if (in_array($provider->id(), ['topcraft', 'hotmc', 'minecraft-rating'], true)) {
            $fields = $provider->id() === 'hotmc' ? ['nick', 'time', 'sign'] : ['username', 'timestamp', 'signature'];
            $data = $request->validate([
                $fields[0] => ['required', 'string', 'regex:/^[A-Za-z0-9_]{1,32}$/D'],
                $fields[1] => ['required', 'integer'],
                $fields[2] => ['required', 'string', 'regex:/^[a-fA-F0-9]{40}$/D'],
            ]);
            $name = $data[$fields[0]];
            $timestamp = $data[$fields[1]];
            $signature = sha1($name.$timestamp.$configuration['callback_key']);
            if (!hash_equals($signature, strtolower($data[$fields[2]]))) {
                throw new HttpException(401);
            }
            if (!(abs(time() - (int) $timestamp) <= 300)) {
                throw new HttpException(422);
            }

            return [
                [
                    'player' => $name,
                    'time' => (int) $timestamp,
                    'event' => $provider->id().':'.hash('sha256', $signature),
                ],
            ];
        }
        $data = $request->validate([
            'pingbackkey' => ['required', 'string'],
            'siteid' => ['sometimes', 'integer'],
            'Common' => ['sometimes', 'array', 'min:1', 'max:50'],
        ]);
        if (!hash_equals($configuration['callback_key'], $data['pingbackkey'])) {
            throw new HttpException(401);
        }
        if ($request->has('Common')) {
            if ((int) ($data['siteid'] ?? 0) !== (int) $configuration['server_id']) {
                throw new HttpException(422);
            }
            $entries = [];
            foreach ($data['Common'] as $row) {
                if (!is_array($row)) {
                    throw new HttpException(422);
                }
                $flat = [];
                foreach ($row as $part) {
                    if (!(is_array($part) && count($part) === 1 && !array_intersect_key($flat, $part))) {
                        throw new HttpException(422);
                    }
                    $flat += $part;
                }
                $validator = V::make($flat, [
                    'pb_id' => ['required', 'integer', 'min:1'],
                    'ip' => ['required', 'ip'],
                    'success' => ['required', 'in:0,1'],
                    'pb_name' => ['required', 'string', 'regex:/^[A-Za-z0-9]{64}$/D'],
                ]);
                $flat = $validator->validate();
                if ((string) $flat['success'] === '0') {
                    $entries[] = [
                        'nonce' => $flat['pb_name'],
                        'ip' => $flat['ip'],
                        'event' => 'gtop100:'.$flat['pb_id'],
                    ];
                }
            }

            return $entries;
        }
        $form = $request->validate([
            'VoterIP' => ['required', 'ip'],
            'Successful' => ['required', 'in:0,1'],
            'pingUsername' => ['required', 'string', 'regex:/^[A-Za-z0-9]{64}$/D'],
        ]);

        return (string) $form['Successful'] === '0'
            ? [
                [
                    'nonce' => $form['pingUsername'],
                    'ip' => $form['VoterIP'],
                    'event' => 'gtop100:'.hash('sha256', $form['pingUsername']),
                ],
            ]
            : [];
    }

    private function record(object $site, array $entry): void
    {
        DB::transaction(function () use ($site, $entry) {
            $current = Site::query()->where('id', $site->id)->lockForUpdate()->first();
            if (!($current && $current->enabled && $current->provider === $site->provider)) {
                throw new HttpException(422, __('vote::messages.verification.configuration_invalid'));
            }
            $site = $current;
            $receipt = CallbackReceipt::query()
                ->where('site_id', $site->id)
                ->where('provider_event', $entry['event'])
                ->first();
            if ($receipt) {
                /** @var Challenge|null $challenge */
                $challenge = Challenge::query()->find($receipt->challenge_id);
                if (!$challenge || $challenge->used_at) {
                    return;
                }
            } else {
                $query = Challenge::query()
                    ->where('site_id', $site->id)
                    ->open()
                    ->where('verification_state', '!=', 'rejected');
                if (isset($entry['nonce'])) {
                    $query->where('token', hash('sha256', $entry['nonce']));
                } else {
                    $query
                        ->whereRaw('LOWER(player_name) = ?', [strtolower($entry['player'])])
                        ->where('created_at', '<=', gmdate('Y-m-d H:i:s', $entry['time'] + 60));
                }
                $challenges = $query->lockForUpdate()->limit(2)->get();
                if ($challenges->count() !== 1) {
                    throw new HttpException(409);
                }
                /** @var Challenge|null $challenge */
                $challenge = $challenges->first();
                if (isset($entry['ip'])) {
                    if (inet_pton(Crypt::decryptString($challenge->ip_address)) !== inet_pton($entry['ip'])) {
                        throw new HttpException(422);
                    }
                }
                CallbackReceipt::query()->insert([
                    'site_id' => $site->id,
                    'challenge_id' => $challenge->id,
                    'provider_event' => $entry['event'],
                    'created_at' => now(),
                ]);
            }
            app(VoteService::class)->complete($site, $challenge, $entry['event']);
        }, 3);
    }
}
