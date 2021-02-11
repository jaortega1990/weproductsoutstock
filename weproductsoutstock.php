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
            $this->registerHook('actionProductUpdate') &&
            $this->registerHook('actionValidateOrder');
    }

    public function uninstall()
    {
        Configuration::deleteByName('WEPRODUCTSOUTSTOCK_CATEGORY');

        return parent::uninstall();
    }

    public function getContent()
    {
        if ((bool) Tools::isSubmit('submitWeproductsoutstock') == true) {
            $this->postProcess();
        }

        return $this->renderForm();
    }

    public function renderForm()
    {
        $helper = new HelperForm();

        $helper->show_toolbar = false;
        $helper->table = $this->table;
        $helper->module = $this;
        $helper->default_form_language = $this->context->language->id;
        $helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG', 0);

        $helper->identifier = $this->identifier;
        $helper->submit_action = 'submitWeproductsoutstock';
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false)
            . '&configure=' . $this->name . '&tab_module=' . $this->tab . '&module_name=' . $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');

        return $helper->generateForm(array($this->getConfigForm()));
    }

    protected function getConfigForm()
    {
        return array(
            'form' => array(
                'legend' => array(
                    'title' => $this->l('Settings'),
                    'icon' => 'icon-cogs',
                ),
                'input' => array(
                    array(
                        'type' => 'categories',
                        'label' => $this->l('Tree categories'),
                        'desc' => $this->l('Select the category where the products without stock will be associated'),
                        'name' => 'WEPRODUCTSOUTSTOCK_CATEGORY',
                        'tree' => array(
                            'root_category' => 2,
                            'id' => 'id_category',
                            'name' => 'name_category',
                            'selected_categories' => $this->getConfigFormValues()
                        ),
                    ),
                ),
                'submit' => array(
                    'title' => $this->l('Save'),
                ),
            ),
        );
    }

    protected function getConfigFormValues()
    {
        return array(
            'WEPRODUCTSOUTSTOCK_CATEGORY' => Configuration::get('WEPRODUCTSOUTSTOCK_CATEGORY'),
        );
    }

    protected function postProcess()
    {
        $form_values = $this->getConfigFormValues();

        foreach ($form_values as $key => $val) {
            $val = Tools::getValue($key);
            Configuration::updateValue($key, $val);
        }
    }

    public function hookActionProductUpdate($params)
    {
        $category = (int) Configuration::get('WEPRODUCTSOUTSTOCK_CATEGORY');

        if ($category != 0) {
            $id_product = $params['id_product'];

            $product_stock = Product::getRealQuantity($id_product);
            $product_categories = Product::getProductCategories($id_product);

            $productIsAssociated = in_array($category, $product_categories);

            $product = new Product($id_product);

            if ($productIsAssociated && $product_stock > 0) {
                $product->deleteCategory($category);
                $product->visibility = 'both';
                $product->update();
            } elseif (!$productIsAssociated && $product_stock == 0) {
                $product->addToCategories(array($category));
                $product->visibility = 'none';
                $product->update();
            }
        }
    }

    public function hookActionValidateOrder($params)
    {
        $products = $params['order']->product_list;

        foreach ($products as $product) {
            $this->hookActionProductUpdate($product);
        }
    }
}
