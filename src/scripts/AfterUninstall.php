<?php

use Espo\Core\Container;
use Espo\Core\InjectableFactory;
use Espo\Core\Utils\Config;
use Espo\Core\Utils\Config\ConfigWriter;

class AfterUninstall
{
    public function run(Container $container): void
    {
        $config = $container->getByClass(Config::class);
        $writer = $container->getByClass(InjectableFactory::class)->create(ConfigWriter::class);
        $writer->set('tabList', array_values(array_filter(
            $config->get('tabList') ?? [], fn ($tab) => $tab !== 'CompanyEnrich',
        )));
        $writer->save();
    }
}
