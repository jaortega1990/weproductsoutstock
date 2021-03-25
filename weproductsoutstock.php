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
        Configuration::updateValue('WEPRODUCTSOUTSTOCK_KEEPCATEGORIES', 1);

        return parent::install() &&
            $this->registerHook('actionProductUpdate') &&
            $this->registerHook('actionValidateOrder') &&
            $this->registerHook('actionUpdateQuantity');
    }

    public function uninstall()
    {
        Configuration::deleteByName('WEPRODUCTSOUTSTOCK_CATEGORY');
        Configuration::deleteByName('WEPRODUCTSOUTSTOCK_KEEPCATEGORIES');

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

        $helper->tpl_vars = array(
            'fields_value' => $this->getConfigFormValues(),
            'id_language' => $this->context->language->id
        );

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
                    array(
                        'type' => 'switch',
                        'label' => $this->l('Keep categories'),
                        'desc' => $this->l('Keep categories when product runs out of stock'),
                        'name' => 'WEPRODUCTSOUTSTOCK_KEEPCATEGORIES',
                        'is_bool' => true,
                        'values' => array(
                            array(
                                'id' => 'WEPRODUCTSOUTSTOCK_KEEPCATEGORIES_on',
                                'value' => 1,
                                'label' => $this->l('Yes'),
                            ),
                            array(
                                'id' => 'WEPRODUCTSOUTSTOCK_KEEPCATEGORIES_off',
                                'value' => 0,
                                'label' => $this->l('No'),
                            )
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
            'WEPRODUCTSOUTSTOCK_KEEPCATEGORIES' => Configuration::get('WEPRODUCTSOUTSTOCK_KEEPCATEGORIES'),
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
        $keepCat = (int) Configuration::get('WEPRODUCTSOUTSTOCK_KEEPCATEGORIES');

        if ($category != 0) {
            $id_product = $params['id_product'];

            $product_stock = Product::getRealQuantity($id_product);
            $product_categories = Product::getProductCategories($id_product);

            $productIsAssociated = in_array($category, $product_categories);

            $product = new Product($id_product);

            if ($productIsAssociated && $product_stock > 0) {
                // Si mantener las categorías es SI (=1) entonces si podemos borrar la asociación de SIN STOCK.
                if ($keepCat == 1) {
                    $product->deleteCategory($category);
                    $product->visibility = 'both';
                    $product->update();
                }
            } elseif (!$productIsAssociated && $product_stock == 0) {
                // Asociamos a la categoría puesta en la configuración y después borramos una a una las otras asociaciones (si no hay que mantenerlas).
                $product->addToCategories(array($category));
                if ($keepCat == 0) {
                    foreach ($product_categories as $id_category) {
                        $product->deleteCategory($id_category);
                    }
                    $product->id_category_default = $category;
                }

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

    public function hookActionUpdateQuantity($params)
    {
        $this->hookActionProductUpdate($params);
    }
}
