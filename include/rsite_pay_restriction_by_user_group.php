<?php

use Bitrix\Sale\Services\Base;
use Bitrix\Sale\Internals\Entity;
use Bitrix\Sale\Payment;

class RsitePayRestrictionByUserGroup extends Base\Restriction
{
    const ONLY_FOR_USER_GROUPS_STRING_IDENTIFIER = 'ONLY_FOR_USER_GROUPS';

    /**
     * Какая-то сортировка чего-то.
     *
     * @var int
     * 100 - lightweight - just compare with params
     * 200 - middleweight - may be use base queries
     * 300 - hardweight - use base, and/or hard calculations
     * */
    public static $easeSort = 100;

    /**
     * Типа заголовок ограничения. Отображается в выпадающем списке добавления ограничений и в других местах.
     *
     * @return string
     */
    public static function getClassTitle()
    {
        return 'custom: По группе пользователя'; // Желательно добавлять префикс 'custom: '  или типа того, чтобы потом было сразу понятно что это наша кастомная хрень.
    }

    /**
     * Описание ограничения.
     *
     * @return string
     */
    public static function getClassDescription()
    {
        return 'Для определённых групп пользователей.';
    }

    /**
     * Параметры (поля для заполнения) ограничения, которые будут показаны при создании ограничения.
     *
     * @param int $entityId
     * @return array
     */
    public static function getParamsStructure($entityId = 0)
    {
        // Получаем список всех групп пользователей с сайта.
        $allSiteUserGroups = array();
        $db = \CGroup::GetList($by = 'c_sort', $order = 'asc'); // Выбираем ВСЕ группы с сайта. // https://dev.1c-bitrix.ru/api_help/main/reference/cgroup/getlist.php
        while ($userGroupData = $db->Fetch()) {
            $allSiteUserGroups[$userGroupData['ID']] = $userGroupData['REFERENCE'];
        }

        return array(
            self::ONLY_FOR_USER_GROUPS_STRING_IDENTIFIER => array(
                'TYPE'     => 'ENUM',
                'MULTIPLE' => 'Y',
                'LABEL'    => 'Показывать только этим группам пользователей',
                'OPTIONS'  => $allSiteUserGroups,
            ),
        );
    }

    /**
     * Получение дополнительных параметров для ограничивалки при применении ограничения (например при оформлении заказа или в админке).
     *
     * @param Entity $entity
     * @return mixed
     */
    protected static function extractParams(Entity $entity)
    {
        // $orderPrice = null;
        // $paymentPrice = null;
        $orderUserId = null; // ID пользователя из заказа.

        if ($entity instanceof Payment) {
            /** @var \Bitrix\Sale\PaymentCollection $collection */
            $collection = $entity->getCollection();
            /** @var \Bitrix\Sale\Order $order */
            $order = $collection->getOrder();

            //$orderPrice = $order->getPrice();
            //$paymentPrice = $entity->getField('SUM');
            $orderUserId = $order->getUserId();
        }

        return array(
            // 'PRICE_PAYMENT' => $paymentPrice,
            // 'PRICE_ORDER'   => $orderPrice,
            'ORDER_USER_ID' => $orderUserId,
        );
    }

    /**
     * Процедура непосредственной проверки при применении ограничения (например при оформлении заказа).
     *
     * @param array $params            Данные из self::extractParams()
     * @param array $restrictionParams Данные из сохранённых настроек ограничения (то что админ установил в настройках данного ограничения)
     * @param int   $serviceId         ID этой платёжной системы (для которой применяется данное ограничение).
     * @return bool Смысл: true - показать платёжную систему; false - скрыть.
     */
    public static function check($params, array $restrictionParams, $serviceId = 0)
    {
        global $USER;

        $userId = $params['ORDER_USER_ID'];
        if ($userId) {
            if (count(array_intersect(CUser::GetUserGroup($userId), $restrictionParams[self::ONLY_FOR_USER_GROUPS_STRING_IDENTIFIER])) > 0) {
                return true;
            } else {
                return false;
            }
        }

        return false;
    }
}
