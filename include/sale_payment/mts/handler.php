<?php
namespace Sale\Handlers\PaySystem;

use Bitrix\Main;
use Bitrix\Main\Request;
use Bitrix\Sale;
use Bitrix\Sale\Order;
use Bitrix\Sale\PaySystem;
use Bitrix\Sale\Payment;
use Bitrix\Sale\PriceMaths;
use Bitrix\Sale\Internals\UserBudgetPool;
use Bitrix\Main\Entity\EntityError;
use Bitrix\Main\Localization\Loc;

Loc::loadMessages(__FILE__);

class mtsHandler extends PaySystem\BaseServiceHandler
{
	/**
	 * @param Payment $payment
	 * @param Request $request
	 * @return PaySystem\ServiceResult
	 * @throws \Bitrix\Main\ArgumentOutOfRangeException
	 */
	public function initiatePay(Payment $payment, Request $request = null)
	{
		$result = new PaySystem\ServiceResult();

		/** @var \Bitrix\Sale\PaymentCollection $collection */
		$collection = $payment->getCollection();

		/** @var \Bitrix\Sale\Order $order */
		$order = $collection->getOrder();

		$paymentSum = PriceMaths::roundPrecision($payment->getSum());

		$orderLoad = Sale\Order::load($_GET['ORDER_ID']);
		$userId = $orderLoad->getUserId();
		$arUser = \Bitrix\Main\UserTable::getList(array(
			'filter' => array(
				'=ID' => $userId,
			),
			'limit'=>1,
			'select'=>array('*','UF_BALANCE','UF_INTEGRATION_ID'),
		))->fetch();

		$userBudget = PriceMaths::roundPrecision($arUser['UF_BALANCE']);

		$basket = $orderLoad->getBasket();
		$basketItems = $basket->getBasketItems();
		$purchase = array();
		$key = 0;
		foreach ($basketItems as $basketItem) {
			$purchase[$key]['name'] = $basketItem->getField('NAME');
			$purchase[$key]['quantity'] = $basketItem->getQuantity();
			$purchase[$key]['price'] = $basketItem->getPrice();
			$key++;
		}
		$purchase[$key]['name'] = 'Доставка';
		$purchase[$key]['quantity'] = 1;
		$purchase[$key]['price'] = $orderLoad->getDeliveryPrice();
		$data = ["summaBuy" => $paymentSum, "purchase" => $purchase];
		$integrationId = str_replace("LIMIT_", "", $arUser['UF_INTEGRATION_ID']);

		$data_string = json_encode ($data, JSON_UNESCAPED_UNICODE);
		$curl = curl_init('https://api-test.mtsbank.ru/loan-service-pos/v1/applications/'.$integrationId.'/approveLimit');
		curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "POST");
		curl_setopt($curl, CURLOPT_POSTFIELDS, $data_string);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($curl, CURLOPT_HTTPHEADER, [
			'Content-Type: application/json',
			'Client-Id: web-loan-service-pos'
		]);
		$resCurl = curl_exec($curl);
		curl_close($curl);

		$linkObj = json_decode($resCurl);
		$link = $linkObj->applicationLink;
		if (!empty($link)) {
			$propertyCollection = $orderLoad->getPropertyCollection();
			$propIntegrationId = $propertyCollection->getItemByOrderPropertyId(52);
			$propLimit = $propertyCollection->getItemByOrderPropertyId(53);
			$propLink = $propertyCollection->getItemByOrderPropertyId(54);
			$propIntegrationId->setValue($arUser['UF_INTEGRATION_ID']);
			$propLimit->setValue($arUser['UF_BALANCE']);
			$propLink->setValue($link);
			$orderLoad->save();

			LocalRedirect($link, true);
			echo '<a href="'.$link.'">Ссылка</a>';
		}
		return $result;
	}

	/**
	 * @return array
	 */
	public function getCurrencyList()
	{
		return array();
	}
}
