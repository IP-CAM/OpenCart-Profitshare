<?php
/**
 * Made by : Boian Ivanov
 * Contact : boian.ivanov44@gmail.com
 * Version : 1.0.0
 * 
 * Under MIT Licence.
 * Feel free to use and modify, but give credit where credit's due.
 */
class ModelModuleProfitshare extends Model{
    public function getItemsReady($order_id){
        $items = $this->getOrderProduct($order_id);
        $items_array = array(
            'num_products' => $items->num_rows,
            'order_id' => $order_id
        );
        foreach($items->rows as $product){
            $url = $this->getUrl($product['product_id']);
            $cat = $this->getCategory($product['product_id']);
            $man = $this->getManufacturer($product['product_id']);
            $items_array['products'][] = array(
                'product_id' => $product['product_id'],
                'price'      => round($product['price']/1.2, 2),
                'name'       => $product['name'],
                'link'       => $url,
                'cat_code'   => $cat['category_id'],
                'cat_name'   => $cat['name'],
                'model'      => $product['model'],
                'manu_id'    => $man['manufacturer_id'],
                'manu_name'  => $man['name'],
                'qty'        => $product['quantity'],
            );
        }
        return $items_array;
    }

    public function getCSVReady(){
        $query = $this->db->query("
            SELECT @Pr_ID:= pr.`product_id` AS `id`,
                descr.`name`,
                ptc.category_id,
                cd.`name` category,
                (SELECT cd.name FROM `" . DB_PREFIX . "product` pr
                    LEFT JOIN `" . DB_PREFIX . "product_to_category` ptc ON pr.`product_id` = ptc.`product_id`
                    LEFT JOIN `" . DB_PREFIX . "category_description` cd ON cd.`category_id` = ptc.`category_id`
                    LEFT JOIN `" . DB_PREFIX . "category_path` cp ON cp.`category_id` = ptc.`category_id`
                    WHERE cp.level = 1 
                        AND cp.category_id = cp.path_id  
                        AND cd.language_id = '" . (int)$this->config->get('config_language_id') . "' 
                        AND pr.product_id = @Pr_ID) as parent_category,
                manu.`name` AS `manu`,
                pr.`manufacturer_id` AS `manu_id`,
                pr.`model`,
                IFNULL((SELECT round((price)/1.2,2) FROM `" . DB_PREFIX . "product_special` WHERE product_id = @Pr_ID AND (CURDATE() between `date_start` and `date_end` OR `date_end` LIKE '0000-00-00') ),round((pr.`price`)/1.2, 2)) AS `exc_VAT`,
                IFNULL((SELECT round(price,2) FROM `" . DB_PREFIX . "product_special` WHERE product_id = @Pr_ID AND (CURDATE() between `date_start` and `date_end` OR `date_end` LIKE '0000-00-00') ),round(pr.`price`, 2)) AS `w/ VAT`,
                pr.`quantity` AS `stock`,
                (SELECT max(keyword) FROM `" . DB_PREFIX . "url_alias` WHERE `query` LIKE CONCAT(\"product_id=\", @Pr_ID) AND language_id = '" . (int)$this->config->get('config_language_id') . "') as link,
                pr.image,
                pr.status
            FROM `" . DB_PREFIX . "product` pr
            LEFT JOIN `" . DB_PREFIX . "product_description` descr ON pr.`product_id` = descr.`product_id` 
            LEFT JOIN `" . DB_PREFIX . "manufacturer` manu ON pr.`manufacturer_id` = manu.`manufacturer_id`
            LEFT JOIN `" . DB_PREFIX . "product_to_category` ptc ON pr.`product_id` = ptc.`product_id`
            LEFT JOIN `" . DB_PREFIX . "category_description` cd ON cd.`category_id` = ptc.`category_id`
            LEFT JOIN `" . DB_PREFIX . "category_path` cp ON cp.`category_id` = ptc.`category_id` 
            WHERE `status` = 1 
                AND level = 2 
                AND descr.`language_id` = '" . (int)$this->config->get('config_language_id') . "' 
                AND cd.`language_id` = '" . (int)$this->config->get('config_language_id') . "'
            ORDER BY id
        ");

        return $query->rows;
    }

    private function getManufacturer($product_id){
        $query = $this->db->query("SELECT pr.manufacturer_id, man.name FROM `" . DB_PREFIX . "product` pr
                                    LEFT JOIN `" . DB_PREFIX . "manufacturer` man ON pr.`manufacturer_id` = man.`manufacturer_id`
                                    WHERE pr.product_id = ".$product_id);
        return $query->rows[0];
    }

    private function getCategory($product_id){
        $query = $this->db->query("SELECT ptc.`category_id`, cd.`name`
                                    FROM  `" . DB_PREFIX . "product_to_category` ptc
                                    LEFT JOIN  `" . DB_PREFIX . "category_description` cd ON cd.category_id = ptc.category_id
                                    LEFT JOIN  `" . DB_PREFIX . "category_path` cp ON cp.category_id = ptc.category_id
                                    WHERE cd.`language_id` = '" . (int)$this->config->get('config_language_id') . "' and ptc.`product_id` = ".$product_id."
                                    AND cp.level = 2 ");
        return $query->rows[0];
    }

    private function getUrl($product_id){
        $query = $this->db->query("SELECT keyword FROM  `" . DB_PREFIX . "url_alias` WHERE query =  'product_id=".$product_id."' and language_id = '" . (int)$this->config->get('config_language_id') . "'");
        $row = $query->rows[0];
        $protocol = stripos($_SERVER['SERVER_PROTOCOL'],'https') === true ? 'https://' : 'http://';
        $url = $protocol.$_SERVER[HTTP_HOST].'/'.$row['keyword'];
        return $url;
    }

    private function getOrderProduct($order_id) {
        $query = $this->db->query("SELECT * FROM " . DB_PREFIX . "order_product WHERE order_id = '" . (int)$order_id . "'");
        return $query;
    }
}
