<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
require "conn.php";


class store_operations
{
    private $conn;

    public function __construct()
    {
        $this->conn = DatabaseConnection::getInstance()->getConnection();
    }


    public function getItem($json)
    {
        $json = json_decode($json, true);
        try {
            $sql = "SELECT * FROM `products` WHERE barcode = :barcode";
            $stmt = $this->conn->prepare($sql);
            $stmt->bindParam(":barcode", $json["barcode"]);
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($result) {
                echo json_encode(array("success" => $result));
            } else {
                echo json_encode(array("error" => "No such item"));
            }
        } catch (PDOException $e) {
            echo json_encode(array("error" => $e->getMessage()));
        }
    }

    public function holdItems($json)
    {
        $json = json_decode($json, true);

        try {
            $sql = "INSERT INTO `heldorders`(`customer_id`, `cashier_id`, `created_at`, `updated_at`, `status`) VALUES (:customer_id, :cashier_id, :created_at, :updated_at, :status)";
            $stmt = $this->conn->prepare($sql);
            $stmt->execute([
                ':customer_id' => $json['customer_id'],
                ':cashier_id' => $json['cashier_id'],
                ':created_at' => date('Y-m-d H:i:s'),
                ':updated_at' => date('Y-m-d H:i:s'),
                ':status' => 'active'
            ]);


            $held_order_id = $this->conn->lastInsertId();


            $itemSql = "INSERT INTO `heldorderitems`(`held_order_id`, `product_id`, `quantity`, `price_at_time`) VALUES (:held_order_id, :product_id, :quantity, :price_at_time)";
            $itemStmt = $this->conn->prepare($itemSql);

            foreach ($json['items'] as $item) {
                $itemStmt->execute([
                    ':held_order_id' => $held_order_id,
                    ':product_id' => $item['product_id'],
                    ':quantity' => $item['quantity'],
                    ':price_at_time' => $item['price_at_time']
                ]);
            }

            echo json_encode(array("success" => "Order held successfully"));

        } catch (PDOException $e) {
            echo json_encode(array("error" => $e->getMessage()));
        }
    }
    public function getHeldItems($json)
    {
        $json = json_decode($json, true);
        try {

            $sql = "
                SELECT heldorders.held_order_id, heldorders.customer_id, heldorders.created_at, heldorders.updated_at, heldorders.status,
                       heldorderitems.held_order_item_id, heldorderitems.product_id, heldorderitems.price_at_time, heldorderitems.quantity,
                       products.name, products.barcode, products.price
                FROM heldorders
                INNER JOIN heldorderitems ON heldorders.held_order_id = heldorderitems.held_order_id
                LEFT JOIN products ON heldorderitems.product_id = products.id  -- join with products table if needed
                WHERE heldorders.status = 'active' AND heldorders.cashier_id = :cashier_id
                ORDER BY heldorders.created_at DESC
            ";

            $stmt = $this->conn->prepare($sql);
            $stmt->bindParam(":cashier_id", $json["cashier_id"]);
            $stmt->execute();
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode(array("success" => $results));

        } catch (PDOException $e) {
            return "Error: " . $e->getMessage();
        }
    }

    public function getAllCustomerID()
    {
        try {
            $sql = "SELECT `customer_id` FROM `heldorders`";
            $stmt = $this->conn->prepare($sql);
            $stmt->execute();
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(array("success" => $results));
        } catch (PDOException $e) {
            echo json_encode(array("error" => $e->getMessage()));
        }
    }

    public function getHeldItemsByCustomer($json)
    {

        $json = json_decode($json, true);

        $customer_id = isset($json["customer_id"]) ? $json["customer_id"] : null;
        $cashier_id = isset($json["cashier_id"]) ? $json["cashier_id"] : null;

        try {
            if (!$customer_id || !$cashier_id) {
                echo json_encode(array("error" => "Missing customer_id or cashier_id"));
                return;
            }

            $sql = "
                SELECT heldorders.held_order_id, heldorders.customer_id, heldorders.created_at, heldorders.updated_at, heldorders.status,
                       heldorderitems.held_order_item_id, heldorderitems.product_id, heldorderitems.price_at_time, heldorderitems.quantity,
                       products.name, products.barcode, products.price
                FROM heldorders
                INNER JOIN heldorderitems ON heldorders.held_order_id = heldorderitems.held_order_id
                LEFT JOIN products ON heldorderitems.product_id = products.id
                WHERE heldorders.status = 'active' 
                  AND heldorders.customer_id = :customer_id
                  AND heldorders.cashier_id = :cashier_id
                ORDER BY heldorders.created_at DESC
            ";

            $stmt = $this->conn->prepare($sql);
            $stmt->bindParam(":customer_id", $customer_id);
            $stmt->bindParam(":cashier_id", $cashier_id);
            $stmt->execute();

            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(array("success" => $results));

        } catch (PDOException $e) {
            echo json_encode(array("error" => "Error: " . $e->getMessage()));
        }
    }

}



$store_operations = new store_operations();


if ($_SERVER["REQUEST_METHOD"] == "GET" || $_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_REQUEST['operation']) && isset($_REQUEST['json'])) {
        $operation = $_REQUEST['operation'];
        $json = $_REQUEST['json'];

        switch ($operation) {
            case 'getItem':
                echo $store_operations->getItem($json);
                break;
            case 'holdItems':
                echo $store_operations->holdItems($json);
                break;
            case 'getHoldItems':
                echo $store_operations->getHeldItems($json);
                break;
            case 'getAllCustomerID':
                echo $store_operations->getAllCustomerID();
                break;
            case 'getHeldItemsFromCustomer':
                echo $store_operations->getHeldItemsByCustomer($json);
                break;

            default:
                echo json_encode(["error" => "Invalid operation"]);
                break;
        }
    } else {
        echo json_encode(["error" => "Missing parameters"]);
    }
} else {
    echo json_encode(["error" => "Invalid request method"]);
}
?>