<?php
/**
 * Ecom Referrer Policy
 *
 * Emite la cabecera HTTP Referrer-Policy en la tienda y en el panel.
 *
 * @author    Ecom Experts <ecomyseo@gmail.com>
 * @copyright 2026 Ecom Experts
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License 3.0 (AFL-3.0)
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

require_once _PS_MODULE_DIR_ . 'ecom_referrerpolicy/classes/EcomReferrerpolicyHeader.php';

class Ecom_Referrerpolicy extends Module
{
    public function __construct()
    {
        $this->name = 'ecom_referrerpolicy';
        $this->tab = 'administration';
        $this->version = '1.0.0';
        $this->author = 'Ecom Experts';
        $this->need_instance = 0;
        $this->bootstrap = true;
        $this->ps_versions_compliancy = array('min' => '1.7.8.0', 'max' => _PS_VERSION_);

        parent::__construct();

        $this->displayName = $this->trans('Referrer-Policy header', array(), 'Modules.Ecomreferrerpolicy.Admin');
        $this->description = $this->trans('Sends the Referrer-Policy HTTP header in the shop and in the back office, so that the back office address and its tokens do not leak through the Referer header.', array(), 'Modules.Ecomreferrerpolicy.Admin');
        $this->confirmUninstall = $this->trans('Are you sure you want to uninstall this module? The Referrer-Policy header will no longer be sent.', array(), 'Modules.Ecomreferrerpolicy.Admin');
    }

    public function isUsingNewTranslationSystem()
    {
        return true;
    }

    public function install()
    {
        if (Shop::isFeatureActive()) {
            Shop::setContext(Shop::CONTEXT_ALL);
        }

        if (!parent::install()) {
            return false;
        }

        $this->addnewfeatures();

        return (bool) $this->isRegisteredInHook('actionDispatcherBefore');
    }

    public function uninstall()
    {
        foreach (array_keys(EcomReferrerpolicyHeader::getDefaults()) as $key) {
            Configuration::deleteByName($key);
        }

        return parent::uninstall();
    }

    /**
     * Altas de hook y valores de fábrica. Se llama al instalar y al entrar en la configuración.
     *
     * @return bool
     */
    public function addnewfeatures()
    {
        try {
            foreach (array('actionDispatcherBefore', 'actionAdminStatusAfter') as $hook) {
                if (!$this->isRegisteredInHook($hook)) {
                    $this->registerHook($hook);
                }
            }

            foreach (EcomReferrerpolicyHeader::getDefaults() as $key => $value) {
                if (Configuration::get($key, null, 0, 0) === false) {
                    Configuration::updateGlobalValue($key, $value);
                }
            }
        } catch (\Throwable $e) {
            return false;
        }

        return true;
    }

    /**
     * Único punto de enganche: corre antes de cualquier salida en la tienda y en el panel,
     * también en las páginas Symfony del panel de PrestaShop 9.
     *
     * @param array $params
     */
    public function hookActionDispatcherBefore(array $params)
    {
        try {
            EcomReferrerpolicyHeader::send(defined('_PS_ADMIN_DIR_'));
        } catch (\Throwable $e) {
            // Una cabecera de seguridad nunca puede tumbar la página.
        }
    }

    /**
     * Al activar o desactivar un registro en un listado antiguo del panel, el núcleo decide a
     * dónde volver leyendo el Referer. Con no-referrer, strict-origin u origin ese Referer no
     * trae el controlador y la vuelta sale rota: aquí se repone la del propio listado.
     *
     * @param array $params
     */
    public function hookActionAdminStatusAfter(array $params)
    {
        try {
            $controller = isset($params['controller']) ? $params['controller'] : null;
            if (!($controller instanceof AdminController) || !empty($controller->errors)) {
                return;
            }

            $referer = isset($_SERVER['HTTP_REFERER']) ? (string) $_SERVER['HTTP_REFERER'] : '';
            if (EcomReferrerpolicyHeader::refererNamesController($referer)) {
                return;
            }

            $controller->setRedirectAfter(EcomReferrerpolicyHeader::buildStatusRedirect(
                AdminController::$currentIndex,
                $controller->token,
                $controller->table,
                Tools::getValue('id_category'),
                Tools::getValue('id_product'),
                Tools::getValue('page')
            ));
        } catch (\Throwable $e) {
            // Si no se puede reponer, se queda la vuelta que haya decidido el núcleo.
        }
    }

    public function getContent()
    {
        $this->addnewfeatures();

        $output = $this->postProcess();
        $output .= $this->renderHeader();

        return $output . $this->renderForm();
    }

    /**
     * Guarda la configuración y comprueba, releyendo la base de datos, que ha quedado guardada.
     *
     * @return string
     */
    protected function postProcess()
    {
        if (!Tools::isSubmit('submitErpConfig')) {
            return '';
        }

        $policyFront = strtolower(trim((string) Tools::getValue('ERP_POLICY_FRONT')));
        $policyBack = strtolower(trim((string) Tools::getValue('ERP_POLICY_BACK')));
        $errors = '';

        if (!EcomReferrerpolicyHeader::isValid($policyFront)) {
            $errors .= $this->displayError($this->trans('The value chosen for the shop is not a valid policy.', array(), 'Modules.Ecomreferrerpolicy.Admin'));
        }

        if (!EcomReferrerpolicyHeader::isValid($policyBack)) {
            $errors .= $this->displayError($this->trans('The value chosen for the back office is not a valid policy.', array(), 'Modules.Ecomreferrerpolicy.Admin'));
        }

        if ($errors !== '') {
            return $errors;
        }

        $values = array(
            'ERP_ENABLE_FRONT' => (int) Tools::getValue('ERP_ENABLE_FRONT') ? 1 : 0,
            'ERP_POLICY_FRONT' => $policyFront,
            'ERP_ENABLE_BACK' => (int) Tools::getValue('ERP_ENABLE_BACK') ? 1 : 0,
            'ERP_POLICY_BACK' => $policyBack,
        );

        foreach ($values as $key => $value) {
            if (EcomReferrerpolicyHeader::isGlobalKey($key)) {
                Configuration::updateGlobalValue($key, $value);
            } else {
                Configuration::updateValue($key, $value);
            }
        }

        foreach ($values as $key => $value) {
            if ((string) $this->readFromDatabase($key) !== (string) $value) {
                return $this->displayError($this->trans('The settings could not be saved: the database does not hold the values that were sent.', array(), 'Modules.Ecomreferrerpolicy.Admin'));
            }
        }

        return $this->displayConfirmation($this->trans('Settings saved and verified in the database.', array(), 'Modules.Ecomreferrerpolicy.Admin'));
    }

    /**
     * Lee el valor de la tabla, sin pasar por la caché de Configuration.
     *
     * @param string $key
     *
     * @return string|false
     */
    protected function readFromDatabase($key)
    {
        $idShop = null;
        $idShopGroup = null;

        if (!EcomReferrerpolicyHeader::isGlobalKey($key)) {
            $idShop = Shop::getContextShopID(true);
            $idShopGroup = Shop::getContextShopGroupID(true);
        }

        if ($idShop) {
            $where = ' AND `id_shop` = ' . (int) $idShop;
        } elseif ($idShopGroup) {
            $where = ' AND `id_shop_group` = ' . (int) $idShopGroup . ' AND (`id_shop` IS NULL OR `id_shop` = 0)';
        } else {
            $where = ' AND (`id_shop_group` IS NULL OR `id_shop_group` = 0) AND (`id_shop` IS NULL OR `id_shop` = 0)';
        }

        return Db::getInstance()->getValue(
            'SELECT `value` FROM `' . _DB_PREFIX_ . 'configuration` WHERE `name` = \'' . pSQL($key) . '\'' . $where,
            false
        );
    }

    /**
     * Estado actual y ayuda plegada, encima del formulario.
     *
     * @return string
     */
    protected function renderHeader()
    {
        $controller = $this->context->controller;
        if (is_object($controller) && method_exists($controller, 'addCSS') && method_exists($controller, 'addJS')) {
            $controller->addCSS($this->_path . 'views/css/admin.css');
            $controller->addJS($this->_path . 'views/js/admin.js');
        }

        $status = EcomReferrerpolicyHeader::getStatus();

        $this->context->smarty->assign(array(
            'erp_front_enabled' => (bool) (int) Configuration::get('ERP_ENABLE_FRONT'),
            'erp_front_policy' => EcomReferrerpolicyHeader::sanitize(
                Configuration::get('ERP_POLICY_FRONT'),
                EcomReferrerpolicyHeader::DEFAULT_FRONT
            ),
            'erp_back_enabled' => (bool) (int) Configuration::get('ERP_ENABLE_BACK', null, 0, 0),
            'erp_back_policy' => EcomReferrerpolicyHeader::sanitize(
                Configuration::get('ERP_POLICY_BACK', null, 0, 0),
                EcomReferrerpolicyHeader::DEFAULT_BACK
            ),
            'erp_multishop' => (bool) Shop::isFeatureActive(),
            'erp_foreign_found' => $status === EcomReferrerpolicyHeader::STATUS_EXISTS,
            'erp_foreign_value' => EcomReferrerpolicyHeader::getValue(),
            'erp_too_late' => $status === EcomReferrerpolicyHeader::STATUS_TOO_LATE,
        ));

        return $this->display(__FILE__, 'views/templates/admin/configure.tpl');
    }

    /**
     * Un único formulario con dos pestañas: tienda y panel.
     *
     * @return string
     */
    protected function renderForm()
    {
        $options = $this->getPolicyOptions();
        $yes = $this->trans('Yes', array(), 'Admin.Global');
        $no = $this->trans('No', array(), 'Admin.Global');

        $fieldsForm = array(
            'form' => array(
                'legend' => array(
                    'title' => $this->trans('Settings', array(), 'Modules.Ecomreferrerpolicy.Admin'),
                    'icon' => 'icon-cogs',
                ),
                'tabs' => array(
                    'erpfront' => $this->trans('Shop', array(), 'Modules.Ecomreferrerpolicy.Admin'),
                    'erpback' => $this->trans('Back office', array(), 'Modules.Ecomreferrerpolicy.Admin'),
                ),
                'input' => array(
                    array(
                        'type' => 'switch',
                        'tab' => 'erpfront',
                        'label' => $this->trans('Send the header in the shop', array(), 'Modules.Ecomreferrerpolicy.Admin'),
                        'name' => 'ERP_ENABLE_FRONT',
                        'is_bool' => true,
                        'desc' => $this->trans('Adds Referrer-Policy to every page of the shop.', array(), 'Modules.Ecomreferrerpolicy.Admin'),
                        'values' => array(
                            array('id' => 'ERP_ENABLE_FRONT_on', 'value' => 1, 'label' => $yes),
                            array('id' => 'ERP_ENABLE_FRONT_off', 'value' => 0, 'label' => $no),
                        ),
                    ),
                    array(
                        'type' => 'select',
                        'tab' => 'erpfront',
                        'label' => $this->trans('Value for the shop', array(), 'Modules.Ecomreferrerpolicy.Admin'),
                        'name' => 'ERP_POLICY_FRONT',
                        'desc' => $this->trans('Recommended: strict-origin-when-cross-origin, which keeps analytics and affiliate tracking working.', array(), 'Modules.Ecomreferrerpolicy.Admin'),
                        'options' => array(
                            'query' => $options,
                            'id' => 'id',
                            'name' => 'name',
                        ),
                    ),
                    array(
                        'type' => 'switch',
                        'tab' => 'erpback',
                        'label' => $this->trans('Send the header in the back office', array(), 'Modules.Ecomreferrerpolicy.Admin'),
                        'name' => 'ERP_ENABLE_BACK',
                        'is_bool' => true,
                        'desc' => $this->trans('Adds Referrer-Policy to every page of the back office, the login page included.', array(), 'Modules.Ecomreferrerpolicy.Admin'),
                        'values' => array(
                            array('id' => 'ERP_ENABLE_BACK_on', 'value' => 1, 'label' => $yes),
                            array('id' => 'ERP_ENABLE_BACK_off', 'value' => 0, 'label' => $no),
                        ),
                    ),
                    array(
                        'type' => 'select',
                        'tab' => 'erpback',
                        'label' => $this->trans('Value for the back office', array(), 'Modules.Ecomreferrerpolicy.Admin'),
                        'name' => 'ERP_POLICY_BACK',
                        'desc' => $this->trans('Recommended: no-referrer, because the back office address is secret.', array(), 'Modules.Ecomreferrerpolicy.Admin'),
                        'options' => array(
                            'query' => $options,
                            'id' => 'id',
                            'name' => 'name',
                        ),
                    ),
                ),
                'submit' => array(
                    'title' => $this->trans('Save', array(), 'Admin.Actions'),
                ),
            ),
        );

        $helper = new HelperForm();
        $helper->show_toolbar = false;
        $helper->table = $this->table;
        $helper->module = $this;
        $helper->default_form_language = (int) Configuration::get('PS_LANG_DEFAULT');
        $helper->allow_employee_form_lang = (int) Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG');
        $helper->identifier = $this->identifier;
        $helper->submit_action = 'submitErpConfig';
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false)
            . '&configure=' . $this->name . '&tab_module=' . $this->tab . '&module_name=' . $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->tpl_vars = array(
            'fields_value' => $this->getConfigFormValues(),
            'languages' => $this->context->controller->getLanguages(),
            'id_language' => (int) $this->context->language->id,
        );

        return $helper->generateForm(array($fieldsForm));
    }

    /**
     * @return array
     */
    protected function getConfigFormValues()
    {
        return array(
            'ERP_ENABLE_FRONT' => (int) Configuration::get('ERP_ENABLE_FRONT'),
            'ERP_POLICY_FRONT' => EcomReferrerpolicyHeader::sanitize(
                Configuration::get('ERP_POLICY_FRONT'),
                EcomReferrerpolicyHeader::DEFAULT_FRONT
            ),
            'ERP_ENABLE_BACK' => (int) Configuration::get('ERP_ENABLE_BACK', null, 0, 0),
            'ERP_POLICY_BACK' => EcomReferrerpolicyHeader::sanitize(
                Configuration::get('ERP_POLICY_BACK', null, 0, 0),
                EcomReferrerpolicyHeader::DEFAULT_BACK
            ),
        );
    }

    /**
     * Las opciones del desplegable, con los mismos valores que admite EcomReferrerpolicyHeader.
     *
     * @return array
     */
    protected function getPolicyOptions()
    {
        return array(
            array(
                'id' => 'no-referrer',
                'name' => $this->trans('no-referrer: the Referer header is never sent', array(), 'Modules.Ecomreferrerpolicy.Admin'),
            ),
            array(
                'id' => 'same-origin',
                'name' => $this->trans('same-origin: full address inside this site, nothing to other sites', array(), 'Modules.Ecomreferrerpolicy.Admin'),
            ),
            array(
                'id' => 'strict-origin',
                'name' => $this->trans('strict-origin: only the domain, and nothing when going from HTTPS to HTTP', array(), 'Modules.Ecomreferrerpolicy.Admin'),
            ),
            array(
                'id' => 'strict-origin-when-cross-origin',
                'name' => $this->trans('strict-origin-when-cross-origin: full address inside this site, only the domain to other sites', array(), 'Modules.Ecomreferrerpolicy.Admin'),
            ),
            array(
                'id' => 'origin',
                'name' => $this->trans('origin: only the domain, always', array(), 'Modules.Ecomreferrerpolicy.Admin'),
            ),
        );
    }
}
