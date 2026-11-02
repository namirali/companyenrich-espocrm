<?php

use Espo\Core\Container;
use Espo\Core\InjectableFactory;
use Espo\Core\Utils\Config;
use Espo\Core\Utils\Config\ConfigWriter;

class AfterInstall
{
    public function run(Container $container): void
    {
        $config = $container->getByClass(Config::class);
        $tabs = $config->get('tabList') ?? [];
        if (!in_array('CompanyEnrich', $tabs, true)) {
            $tabs[] = 'CompanyEnrich';
            $writer = $container->getByClass(InjectableFactory::class)->create(ConfigWriter::class);
            $writer->set('tabList', $tabs);
            $writer->save();
        }
    }
}
