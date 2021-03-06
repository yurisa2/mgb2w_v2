<?php
class b2w_product extends products
{
  public function updateproduct()
  {
    $return = $this->get_products();
    $last_product_updated = "include/files/last_update_product.json";

    if(!strpos($return->response_status_lines[0], "20")){
      if(!strpos($return->response_status_lines[0], "50")) {
        if($return->response_status_lines[0] == '') $response_httpStatus = $return->error;
        else $response_httpStatus = $return->response_status_lines[0];
        mandaEmail_files_db("GET(products)",$response_httpStatus,'Erro no ciclo de update SKYHUB');
        return false;
      } else {
        if($return->response_status_lines[0] == '') $response_httpStatus = $return->error;
        else $response_httpStatus = $return->response_status_lines[0];
        $error_handling = new error_handling("GET(products)", $response_httpStatus, "Erro no ciclo de update SKYHUB", "erro");
        //o erro no json de log  error_files/error_log.json
        //executa a função para criar a mensagem de erro
        $error_handling->send_errorlog_email();
        //executa a função para atualizar o json com o novo erro
        $error_handling->files();
        exit();
      }
    }
    $products = json_decode($return->response);
    foreach ($products->products as $key => $value) $array_skus[] = $value->sku;

    //if file not exists, create it
    if(!file_exists($last_product_updated)) file_put_contents($last_product_updated, $array_skus[0]);

    //get the contents of file
    $sku = trim(file_get_contents($last_product_updated));
    if($sku == "")
    {
      unlink($last_product_updated);
      exit;
    }

    $key = array_search($sku,$array_skus);

    //write next sku in file
    file_put_contents($last_product_updated, $array_skus[$key+1]);

    // if the position of sku is the last, write in file the first sku
    if(count($array_skus) == $key+1)  file_put_contents($last_product_updated, $array_skus[0]);

    $produto = magento_catalogProductInfo($sku);
    $qty = round(magento_catalogInventoryStockItemList($sku)[0]->qty);
    $price = magento_catalogProductInfo_price($sku);

    $images = magento_catalogProductAttributeMediaList($sku);
    $images_url = array();

    foreach ($images as $key => $value) {
      $images_url[] = $value->url;
    }


    if($qty > 0) $status = "enabled";
    else $status = "disabled";

    $product['sku'] = $sku;
    if(TITLE) $product['name'] = PREFFIX_PROD.$produto->name.SUFFIX_PROD;
    else echo '<h1>TITULO DESATIVADO</h1>';

    if(DESCRIPTION) $product['description'] = magento_catalogProductInfo_description($sku);
    else echo '<h1>DESCRIPTION DESATIVADO</h1>';

    $product['weight'] = $produto->weight;
    if(PRICE) $product['price'] = round(($price * SETTINGS_PRICE_MULTIPLICATION) + SETTINGS_PRICE_ADDITION,2);
    else echo '<h1>PRICE DESATIVADO</h1>';

    if(STOCK) $product['qty'] = $qty;
    else echo '<h1>STOCK DESATIVADO</h1>';

    $product['status'] = $status;
    $product['brand'] = BRAND;
    if(IMAGES) $product['images'] = $images_url;
    else echo '<h1>IMAGES DESATIVADO</h1>';

    $product = array("product" => $product);

    $return = $this->b2w_put->put("products/$sku",$product);
    //Response of PUT requisition is httpCode 100 to continue and another code for success data update or error
    if(count($return->response_status_lines) == 1) {
      if(!strpos($return->response_status_lines[0], "20")) {
        if(!strpos($return->response_status_lines[0], "50")) {
          if($return->error == '') $response_httpStatus = "HTTPCODE 1: ".$return->response_status_lines[0];
          else $response_httpStatus = $return->error;
          mandaEmail_files_db("PUT(products/$sku)","$response_httpStatus",'Erro no ciclo de update SKYHUB');
        }else {
          if($return->error == '') $response_httpStatus = "HTTPCODE 1: ".$return->response_status_lines[0];
          else $response_httpStatus = $return->error;
          $error_handling = new error_handling("PUT(products/$sku)", $response_httpStatus, "Erro no ciclo de update SKYHUB", "erro");
          //o erro no json de log  error_files/error_log.json
          //executa a função para criar a mensagem de erro
          $error_handling->send_errorlog_email();
          //executa a função para atualizar o json com o novo erro
          $error_handling->files();
          exit();
        }
      }
    }else {
      if($return->response_status_lines[0] != "HTTP/1.1 100 Continue" || $return->response_status_lines[1] != "HTTP/1.1 204 No Content"){
        if(!strpos($return->response_status_lines[0], "50") || !strpos($return->response_status_lines[1], "50") ) {
          if($return->error == '') $response_httpStatus = $response_httpStatus = "HTTPCODE 1: ".$return->response_status_lines[0]."HTTPCODE 2: ".$return->response_status_lines[1];
          else $response_httpStatus = $return->error;
          mandaEmail_files_db("PUT(products/$sku)","$response_httpStatus",'Erro no ciclo de update SKYHUB');
        }else {
          if($return->error == '') $response_httpStatus = $response_httpStatus = "HTTPCODE 1: ".$return->response_status_lines[0]."HTTPCODE 2: ".$return->response_status_lines[1];
          else $response_httpStatus = $return->error;
          $error_handling = new error_handling("PUT(products/$sku)", $response_httpStatus, "Erro no ciclo de update SKYHUB", "erro");
          //o erro no json de log  error_files/error_log.json
          //executa a função para criar a mensagem de erro
          $error_handling->send_errorlog_email();
          //executa a função para atualizar o json com o novo erro
          $error_handling->files();
          exit();
        }
      }
    }
    if($return->error != "") {
      var_dump($return->error);
      return false;
    } else return "$sku OK!";
  }
}
?>
