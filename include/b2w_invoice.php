<?php
class b2w_invoice extends order
{
  public function verify_envoice()
  {
    $order_data = $this->get_orders()->orders;
    // echo "<h2>Id dos pedidos B2W:</h2> <br>";
    // var_dump($order_data);

    if($order_data !== "false")
    {
      foreach ($order_data as $key => $value)
      {
        $ids_order[] = array('updated_at' => $order_data[$key]->updated_at,'code' => $order_data[$key]->code);
        $list_order = $ids_order;
      }
      sort($list_order);

      foreach ($list_order as $key => $value) {

        $ids[] = $value['code'];
      }
      $list_orderids = $ids;
      // var_dump($list_orderids);
      if(!file_exists("include/files/last_verified_order.json")) file_put_contents("include/files/last_verified_order.json",json_encode($list_orderids[0]));

      $last_order_verified = json_decode(file_get_contents("include/files/last_verified_order.json"));

      $index = array_search($last_order_verified,$list_orderids);
      if($index+1 == count($list_orderids)) return false;
      else $nextorder_to_verify = $list_orderids[$index+1];

      if(!file_exists("include/files/increment.json")) file_put_contents("include/files/increment.json", json_encode(1));
      $qtd_script = json_decode(file_get_contents("include/files/increment.json"));

      echo "<h3>Verificando Pedido: ".$nextorder_to_verify." NFE</h3><br>";
      if($qtd_script % VERIFY_NFE == 0){
        $order_data = $this->get_order_information($nextorder_to_verify);
        if((empty($order_data->invoices)) && ($order_data->status->type == "APPROVED")){
          $produto = '';
          foreach ($order_data->items as $key => $value) {
            $produto.= $order_data->items[$key]->name." ";
          }
          $server_name = $_SERVER['SERVER_NAME'];
          //MANDA POR EMAIL LINK PARA ADICIONAR A CHAVE NFE AO PEDIDO
          $error_handling = new log("Pedido B2W sem NFE", "Pedido sem chave NFE", " Favor visitar o link: http://$server_name/conectores/mgb2w/nfe.php", "pedido sem NFE");
          $error_handling->log_email = true;
          $error_handling->mensagem_email = "Pedido sem a chave da nota fiscal";
          $error_handling->log_email = true;
          $error_handling->email_nfe = true;
          $error_handling->dir_file = "log/log.json";
          $error_handling->log_files = true;
          $error_handling->send_log_email();
          $error_handling->execute();

          return "Há pedidos sem a chave da nota fiscal";
          // file_put_contents("include/files/last_verified_order.json",json_encode($nextorder_to_verify));
        } else {
          echo "Não há pedidos sem a chave da NFE<br>";
          file_put_contents("include/files/last_verified_order.json",json_encode($nextorder_to_verify));
          return "Não há pedidos sem a chave da nota fiscal";
        }
      } else {
        $qtd_script++;
        $put_qtd_script = file_put_contents("include/files/increment.json",$qtd_script);

        return "É verificado uma vez ao dia. Quando o contador ficar em ".VERIFY_NFE.". Atualmente em :".$qtd_script;
      }
    }
  }
}
?>
