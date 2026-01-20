<?php require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/prolog_before.php"); ?>

<?php
$result = [];
$webhook_data = file_get_contents('php://input');
$data = json_decode($webhook_data, true);
if (!empty($data)) {
    $result["request"] = $data;
    $arrField = [
        "id" => "Отсутствует integrationId",
        "orderNumber" => "Отсутствует orderNumber",
        "posLimit" => "Отсутствует posLimit",
        "status" => "Отсутствует status",
    ];
    foreach ($arrField as $key => $field) {
        if (empty($data[$key])) {
            $result["error"][] = $field;
            break;
        }
    }

    $limit = null;
    $integrationId = null;
    $updateStatus = false;
    $updateOrderID = false;
    $userGUID = $data["orderNumber"];
    switch ($data["status"]) {
        case "Отказ банка":
        case "Отказ клиента":
            $limit = null;
            $integrationId = null;
            break;
        case "Одобрен лимит":
        case "Лимит активирован":
        case "Заявка одобрена":
            $limit = $data["posLimit"];
            $integrationId = $data["id"];
            break;
        case "Кредит выдан":
            $updateStatus = true;
            $limit = null;
            $updateOrderID = $data["id"];
            $integrationId = null;
            break;
        case "В работе":
        default:
            break;
    }

    $rsUsers = CUser::GetList(array('sort' => 'asc'), 'sort', ["XML_ID" => $userGUID]);
    if ($userInfo = $rsUsers->GetNext()) {
        $user = new CUser;
        $fields = array(
            "UF_BALANCE" => $limit,
            "UF_INTEGRATION_ID" => $integrationId,
        );
        $user->Update($userInfo["ID"], $fields);
        $result["log"][] = "Обновили пользователя " . $userGUID;
        $updateError = $user->LAST_ERROR;
        if ($updateError){
            $result["error"][] = $updateError;
        }
    } else {
        $result["error"][] = "Пользователь не найден";

    }

    if ($updateStatus != false && $updateOrderID != null) {

        $updateOrderID = $data["id"];
        $order = \Bitrix\Sale\Order::loadByFilter([
            'select' => ['ID'],
            'filter' => [
                //"ID" => 14831,
                "PAYED" => "N",
                "@STATUS_ID" => ["N"],
                "CANCELED" => "N",
                "PROPERTY.CODE" => "MTS_INTEGRATOIN_ID",
                "PROPERTY.VALUE" => $updateOrderID,
            ],
            'order' => ['ID' => 'DESC'],
            'limit' => 1,
        ]);
        if (!empty($order)) {
            foreach ($order as $ord) {
                $ord->setField("STATUS_ID", "P");
                $paymentCollection = $ord->getPaymentCollection();
                foreach ($paymentCollection as $payment) {
                    $payment->setPaid('Y');
                }
                $result["log"][] = 'Обновили статус заказа';
                $ord->save();
            }
        } else {
            $result["error"][] = "Заказа с заявкой " . $updateOrderID . " не найден";
        }
    }
} else {
    $result["error"][] = "Отсутствует объект запроса";
}

echo json_encode($result);
?>

<?php require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/epilog_after.php"); ?>
