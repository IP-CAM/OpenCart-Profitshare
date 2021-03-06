<?php
/**
 * Made by : Boian Ivanov
 * Contact : boian.ivanov44@gmail.com
 * Version : 1.0.0
 * 
 * Under MIT Licence.
 * Feel free to use and modify, but give credit where credit's due.
 */
class ControllerModuleProfitshare extends Controller{
    private $key = "{encoding_key}"; // The key that is assigned from the advertiser to the client
    private $advertiser_code = "{advertiser_code}"; // The advertiser code given by the advertiser
    private $csv_path = "/csv/"; // default directory to csv file
    private $csv_name = "profitshare"; // default csv file name

    public function getPixel(){
        $order_id = $this->request->get['order_id'];
        $protocol = stripos($_SERVER['SERVER_PROTOCOL'],'https') === true ? 'https://' : 'http://';
        if($order_id != $protocol.$_SERVER[HTTP_HOST].'/success') {
            $pixel = "<img src='//profitshare.bg/c/image/1/a/" . $this->advertiser_code . "/p/" . $this->getOrderHash($order_id) . "' alt='' border='' width='1' height='1' style='border:none !important; margin:0px !important;' />";
            $this->response->addHeader('Content-Type: application/json');
            $this->response->setOutput(json_encode($pixel, JSON_UNESCAPED_SLASHES));
        }
    }

    private function setUp($order_id){
        $this->load->model('module/profitshare');
        $model = $this->model_module_profitshare->getItemsReady($order_id);
        $encryption_key = "external_reference=[".$model['order_id']."]&";
        foreach($model['products'] as $product){
            $encryption_key .= "product_code[]=".$product['product_id']."&";
            $encryption_key .= "product_price[]=".$product['price']."&";
            $encryption_key .= "product_name[]=".$product['name']."&";
            $encryption_key .= "product_link[]=".$product['link']."&";
            $encryption_key .= "product_category[]=".$product['cat_code']."&";
            $encryption_key .= "product_category_name[]=".$product['cat_name']."&";
            $encryption_key .= "product_part_no[]=".$product['model']."&";
            $encryption_key .= "product_brand[]=".$product['manu_name']."&";
            $encryption_key .= "product_brand_code[]=".$product['manu_id']."&";
            $encryption_key .= "product_qty[]=".$product['qty'];
            if($product !== end($model['products'])){$encryption_key .= "&";}
        }
        return $encryption_key;
    }

    public function createCSV(){
        $csv_path = $_SERVER['DOCUMENT_ROOT'].$this->csv_path;
        if (!file_exists($csv_path)) {
            mkdir($csv_path, 0777, true);
        }
        $file = fopen($csv_path.$this->csv_name.'.csv', 'w');

        fputcsv($file, array(
            'Category code',
            'Category',
            'Parent category',
            'Manufacturer',
            'Manufacturer Code',
            'Model',
            'Product code',
            'Name',
            'Description',
            'Link',
            'Image',
            'Price excluding Vat',
            'Price including VAT',
            'Price with discount excluding VAT',
            'Currency',
            'Availability',
            'Free delivery',
            'Free gift',
            'Status'
        ));
        $this->load->model('module/profitshare');
        $products = $this->model_module_profitshare->getCSVReady();

        $protocol = stripos($_SERVER['SERVER_PROTOCOL'],'https') === true ? 'https://' : 'http://';

        foreach($products as $product){
            $delivery = $product['w/ VAT'] > 40 ? 1 : 0 ;
            fputcsv($file, array(
                substr($product['category_id'], 0, 19),
                substr($product['category'], 0, 99),
                substr($product['parent_category'], 0, 99),
                substr($product['manu'], 0 ,99),
                substr($product['manu_id'], 0, 24),
                substr($product['model'], 0, 254),
                substr($product['id'], 0, 100),
                substr($product['name'], 0, 254),
                "", /* Description Field */
                $protocol.$_SERVER[HTTP_HOST].'/'.str_replace(' ', '%20', $product['link']),
                $protocol.$_SERVER[HTTP_HOST].'/image/'.str_replace(' ', '%20', $product['image']),
                $product['exc_VAT'],
                $product['w/ VAT'],
                "", /*Price with discount excluding VAT*/
                $this->session->data['currency'],
                "in stock",
                $delivery,
                "0",
                $product['status']
            ));
        }
        return fclose($file);
    }

    private function getOrderHash($order_id) {
        $secretKey = md5($this->key);
        $value = $this->setUp($order_id);
        return rtrim(
            bin2hex(
                mcrypt_encrypt(
                    MCRYPT_RIJNDAEL_256,
                    $secretKey, $value,
                    MCRYPT_MODE_ECB,
                    mcrypt_create_iv(
                        mcrypt_get_iv_size(
                            MCRYPT_RIJNDAEL_256,
                            MCRYPT_MODE_CBC
                        ),
                        MCRYPT_RAND
                    )
                )
            ),
            "\0"
        );
    }
}
