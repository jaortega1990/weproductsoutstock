<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

class Weproductsoutstock extends Module
{
    public function __construct()
    {
        $this->name = 'weproductsoutstock';
        $this->tab = 'other';
        $this->version = '1.0.0';
        $this->author = 'Wecomm Solutions';
        $this->need_instance = 0;

        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->l('Products Out Stock');
        $this->description = $this->l('Associate products without stock to the selected category');

        $this->confirmUninstall = $this->l('Are you sure you want to uninstall this module?');

        $this->ps_versions_compliancy = array('min' => '1.7', 'max' => _PS_VERSION_);
    }

    public function install()
    {
        Configuration::updateValue('WEPRODUCTSOUTSTOCK_CATEGORY', 0);

        return parent::install() &&
            $this->registerHook('actionProducUpdate');
    }

    public function uninstall()
    {
        Configuration::deleteByName('WEPRODUCTSOUTSTOCK_CATEGORY');

        return parent::uninstall();
    }

    public function getContent()
    {
        return 'ola k ase';
    }
}