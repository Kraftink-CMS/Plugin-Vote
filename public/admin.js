const initialized = new WeakSet();
const select = (root, selector) => {
    const elements = [...(root.matches?.(selector) ? [root] : []), ...root.querySelectorAll(selector)];
    return elements.filter((element) => {
        if (initialized.has(element)) {
            return false;
        }
        initialized.add(element);
        return true;
    });
};

const confirmation = (form, required) => {
    const group = form.querySelector('[data-vote-confirmation]');
    if (!group) {
        return;
    }
    group.hidden = !required;
    group.querySelectorAll('input').forEach((input) => {
        input.disabled = !required;
        input.required = required;
    });
};

const fieldValue = (form, name) => {
    const field = form.elements.namedItem(name);
    return field?.type === 'checkbox' ? (field.checked ? '1' : '') : String(field?.value ?? '').trim();
};

const rewardConfirmationRequired = (form, original) => {
    const amount = form.elements.namedItem('wallet_amount');
    const entered = amount ? amount.value.trim().replace(',', '.') : '';
    const parts = /^(\d+)(?:\.(\d{0,2}))?$/.exec(entered);
    const currentAmount = amount
        ? parts
            ? Number(parts[1]) * 100 + Number((parts[2] ?? '').padEnd(2, '0'))
            : 0
        : Number(fieldValue(form, 'wallet_minor'));
    if (currentAmount === 0 && Number(original?.wallet_minor ?? 0) === 0) {
        return false;
    }
    if (!original) {
        return true;
    }
    return Object.entries(original).some(([name, value]) => {
        if (name === 'wallet_minor') {
            return currentAmount !== Number(value ?? 0);
        }
        if (name === 'wallet_currency') {
            return form.dataset.voteWalletCurrency !== value;
        }
        if (name === 'game') {
            return false;
        }
        if (name === 'commands') {
            const commands = fieldValue(form, name)
                .split(/\r?\n/)
                .map((command) => command.trim())
                .filter(Boolean);
            return JSON.stringify(commands) !== JSON.stringify(JSON.parse(value || '[]'));
        }
        const expected = typeof value === 'boolean' ? (value ? '1' : '') : String(value ?? '');
        return fieldValue(form, name) !== expected;
    });
};

const initializeVoteAdmin = (root = document) => {
    select(root, '[data-vote-site-form]').forEach((form) => {
        const selector = form.querySelector('[data-vote-provider]');
        const delay = form.querySelector('[name="cooldown_minutes"]');
        const deliveryMode = form.querySelector('[data-vote-delivery-mode]');
        const votifierConfig = form.querySelector('[data-votifier-config]');
        const votifierCopy = form.querySelector('[data-votifier-copy]');
        const original = JSON.parse(form.dataset.voteSiteOriginal || '{}');
        const updateConfirmation = () => {
            const changedCredential = [...form.querySelectorAll('input')].some(
                (input) =>
                    !input.disabled &&
                    (((input.name === 'callback_secret' || input.name.startsWith('credentials[')) &&
                        input.type === 'password' &&
                        input.value.trim() !== '') ||
                        (input.name.startsWith('clear_credentials[') && input.checked)),
            );
            confirmation(
                form,
                original.new ||
                    changedCredential ||
                    selector.value !== original.provider ||
                    fieldValue(form, 'url') !== original.url ||
                    fieldValue(form, `credentials[${selector.value}][server_id]`) !==
                        String(original.server_id ?? '') ||
                    ['delivery_mode', 'votifier_port', 'votifier_website_name'].some(
                        (name) => fieldValue(form, name) !== String(original[name] ?? '').trim(),
                    ) ||
                    (deliveryMode?.value === 'votifier' &&
                        fieldValue(form, 'server_id') !== String(original.target_server ?? '')),
            );
        };
        const updateDelivery = () => {
            const mode = deliveryMode?.value ?? 'command';
            form.querySelectorAll('[data-vote-delivery-fields]').forEach((group) => {
                const visible = group.dataset.voteDeliveryFields === mode;
                group.hidden = !visible;
                group.querySelectorAll('input, select, textarea').forEach((input) => (input.disabled = !visible));
            });
            if (votifierConfig && votifierCopy) {
                const values = {
                    port: fieldValue(form, 'votifier_port'),
                    website_name: JSON.stringify(fieldValue(form, 'votifier_website_name')),
                    token: JSON.stringify(votifierConfig.dataset.tokenPlaceholder),
                };
                votifierConfig.textContent = (votifierConfig.dataset.votifierTemplate ?? '').replace(
                    /\{(port|website_name|token)\}/g,
                    (_, name) => values[name],
                );
                votifierCopy.dataset.copy = votifierConfig.textContent;
            }
            updateConfirmation();
        };
        const update = () => {
            form.querySelectorAll('[data-vote-provider-fields]').forEach((group) => {
                const visible = group.dataset.voteProviderFields === selector.value;
                group.hidden = !visible;
                group.querySelectorAll('input, select, textarea').forEach((input) => (input.disabled = !visible));
            });
            delay.min = selector.selectedOptions[0].dataset.minimum || '1';
            updateDelivery();
        };
        selector.addEventListener('change', () => {
            update();
            if (Number(delay.value) < Number(delay.min)) {
                delay.value = delay.min;
            }
        });
        form.addEventListener('input', updateConfirmation);
        form.addEventListener('change', updateConfirmation);
        deliveryMode?.addEventListener('change', updateDelivery);
        form.querySelectorAll('[data-votifier-port], [data-votifier-website-name]').forEach((input) =>
            input.addEventListener('input', updateDelivery),
        );
        update();
    });

    select(root, '[data-vote-reward-form]').forEach((form) => {
        const selector = form.querySelector('[name="type"]');
        const original = JSON.parse(form.dataset.voteRewardOriginal || 'null');
        const updateConfirmation = () => confirmation(form, rewardConfirmationRequired(form, original));
        const update = () => {
            form.querySelectorAll('[data-vote-reward-types]').forEach((group) => {
                const visible = group.dataset.voteRewardTypes.split(' ').includes(selector.value);
                group.hidden = !visible;
                group.querySelectorAll('input, select, textarea').forEach((input) => (input.disabled = !visible));
            });
            updateConfirmation();
        };
        selector.addEventListener('change', update);
        form.addEventListener('input', updateConfirmation);
        form.addEventListener('change', updateConfirmation);
        update();
    });
};

initializeVoteAdmin();
document.addEventListener('kraft:content-added', (event) => initializeVoteAdmin(event.target));
