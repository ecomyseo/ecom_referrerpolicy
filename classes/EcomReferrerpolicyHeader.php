<?php
/**
 * Ecom Referrer Policy
 *
 * Decide y emite la cabecera HTTP Referrer-Policy.
 *
 * @author    Ecom Experts <ecomyseo@gmail.com>
 * @copyright 2026 Ecom Experts
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License 3.0 (AFL-3.0)
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

class EcomReferrerpolicyHeader
{
    const HEADER_NAME = 'Referrer-Policy';

    const DEFAULT_FRONT = 'strict-origin-when-cross-origin';
    const DEFAULT_BACK = 'no-referrer';

    const STATUS_SENT = 'sent';
    const STATUS_DISABLED = 'disabled';
    const STATUS_EXISTS = 'exists';
    const STATUS_TOO_LATE = 'too_late';
    const STATUS_CLI = 'cli';

    /** @var string Resultado del intento de esta petición ('' = el hook no ha corrido) */
    protected static $status = '';

    /** @var string Valor emitido, o el de la cabecera ajena que ya existía */
    protected static $value = '';

    /**
     * Valores admitidos, en el orden en que se ofrecen en el formulario.
     *
     * @return array
     */
    public static function getPolicies()
    {
        return array(
            'no-referrer',
            'same-origin',
            'strict-origin',
            'strict-origin-when-cross-origin',
            'origin',
        );
    }

    /**
     * Claves de configuración con su valor de fábrica.
     *
     * @return array
     */
    public static function getDefaults()
    {
        return array(
            'ERP_ENABLE_FRONT' => 1,
            'ERP_POLICY_FRONT' => self::DEFAULT_FRONT,
            'ERP_ENABLE_BACK' => 1,
            'ERP_POLICY_BACK' => self::DEFAULT_BACK,
        );
    }

    /**
     * El panel es uno solo para todas las tiendas: sus claves son globales.
     *
     * @param string $key
     *
     * @return bool
     */
    public static function isGlobalKey($key)
    {
        return in_array($key, array('ERP_ENABLE_BACK', 'ERP_POLICY_BACK'), true);
    }

    /**
     * @param mixed $policy
     *
     * @return bool
     */
    public static function isValid($policy)
    {
        return is_string($policy) && in_array($policy, self::getPolicies(), true);
    }

    /**
     * @param mixed $policy
     * @param string $default
     *
     * @return string
     */
    public static function sanitize($policy, $default)
    {
        $policy = is_scalar($policy) ? strtolower(trim((string) $policy)) : '';

        return self::isValid($policy) ? $policy : $default;
    }

    /**
     * Busca una Referrer-Policy entre las cabeceras ya preparadas por PHP.
     *
     * @param array $headers Lo que devuelve headers_list()
     *
     * @return string|null El valor de la cabecera, o null si no hay ninguna
     */
    public static function findExisting(array $headers)
    {
        foreach ($headers as $line) {
            if (!is_string($line)) {
                continue;
            }

            $pos = strpos($line, ':');
            if ($pos === false) {
                continue;
            }

            if (strtolower(trim(substr($line, 0, $pos))) === strtolower(self::HEADER_NAME)) {
                return trim((string) substr($line, $pos + 1));
            }
        }

        return null;
    }

    /**
     * Decide qué hacer sin tocar nada: se puede probar fuera de PrestaShop.
     *
     * @param bool|int $enabled
     * @param mixed $policy
     * @param string $default
     * @param array $headers
     *
     * @return array [estado, valor]
     */
    public static function decide($enabled, $policy, $default, array $headers)
    {
        if (!$enabled) {
            return array(self::STATUS_DISABLED, '');
        }

        $existing = self::findExisting($headers);
        if ($existing !== null) {
            return array(self::STATUS_EXISTS, $existing);
        }

        return array(self::STATUS_SENT, self::sanitize($policy, $default));
    }

    /**
     * Emite la cabecera una sola vez por petición, y solo si nadie la ha puesto antes.
     *
     * @param bool $isBack true en el panel, false en la tienda
     *
     * @return string Uno de los STATUS_*
     */
    public static function send($isBack)
    {
        if (self::$status !== '') {
            return self::$status;
        }

        if (PHP_SAPI === 'cli') {
            self::$status = self::STATUS_CLI;

            return self::$status;
        }

        if ($isBack) {
            $enabled = (int) Configuration::get('ERP_ENABLE_BACK', null, 0, 0);
            $policy = Configuration::get('ERP_POLICY_BACK', null, 0, 0);
            $default = self::DEFAULT_BACK;
        } else {
            $enabled = (int) Configuration::get('ERP_ENABLE_FRONT');
            $policy = Configuration::get('ERP_POLICY_FRONT');
            $default = self::DEFAULT_FRONT;
        }

        if (headers_sent()) {
            self::$status = self::STATUS_TOO_LATE;

            return self::$status;
        }

        $headers = headers_list();
        list($status, $value) = self::decide($enabled, $policy, $default, is_array($headers) ? $headers : array());

        if ($status === self::STATUS_SENT) {
            header(self::HEADER_NAME . ': ' . $value);
        }

        self::$status = $status;
        self::$value = $value;

        return self::$status;
    }

    /**
     * El núcleo (AdminController::processStatus) busca «controller=» en el Referer para saber
     * a dónde volver tras activar o desactivar un registro. Si no lo trae, la vuelta sale rota.
     *
     * @param mixed $referer
     *
     * @return bool
     */
    public static function refererNamesController($referer)
    {
        return is_string($referer) && preg_match('/[\?|&]controller=([^&]*)/', $referer) === 1;
    }

    /**
     * La misma dirección a la que vuelve el núcleo cuando el Referer es el del propio listado.
     *
     * @param string $currentIndex AdminController::$currentIndex
     * @param string $token
     * @param string $table
     * @param mixed $idCategory
     * @param mixed $idProduct
     * @param mixed $page
     *
     * @return string
     */
    public static function buildStatusRedirect($currentIndex, $token, $table, $idCategory, $idProduct, $page)
    {
        $url = $currentIndex . '&token=' . $token . '&conf=5';

        if ((int) $idCategory && $idProduct) {
            $url .= '&id_category=' . (int) $idCategory;
        }

        if ((int) $page > 1) {
            $url .= '&submitFilter' . $table . '=' . (int) $page;
        }

        return $url;
    }

    /**
     * @return string
     */
    public static function getStatus()
    {
        return self::$status;
    }

    /**
     * @return string
     */
    public static function getValue()
    {
        return self::$value;
    }
}
