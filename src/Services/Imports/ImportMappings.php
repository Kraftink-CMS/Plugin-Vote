<?php

namespace KraftInk\Plugins\Vote\Services\Imports;

use App\Kraft\Migration\MappingRegistry;

final class ImportMappings
{
    public function register(MappingRegistry $registry): void
    {
        $writer = new Writer();
        new Azuriom\Mappings($writer)->register($registry);
        new MineWeb\Mappings($writer)->register($registry);
        new KraftInk\Mappings()->register($registry);
    }
}
