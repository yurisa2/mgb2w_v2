<?php
class b2w_order extends order
{
  public function createorder()
  {
    if(ORDER) {
      $order_data = $this->get_orders()->orders;

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
        echo "<h2>Id dos pedidos B2W:</h2> <br>";
        var_dump(json_encode($list_orderids));  //DEBUG

        if(!file_exists("include/files/last_created_order.json")) file_put_contents("include/files/last_created_order.json",json_encode($ids_order[0]));

        $last_order_created = json_decode(file_get_contents("include/files/last_created_order.json"));

        $index = array_search($last_order_created,$list_orderids);
        if($index+1 == count($list_orderids)) return "Sem novos pedidos";

        $next_order_id = $list_orderids[$index+1];

        echo "<h3>Próximo Pedido: ".$next_order_id."</h3><br>";

        if(!file_exists("include/files/list_magento_orders.json")) file_put_contents("include/files/list_magento_orders.json","");

        $list_magento_orders = json_encode(file_get_contents("include/files/list_magento_orders.json"));

        if(!strpos($list_magento_orders, $next_order_id))
        {
          // PEGA OS DADOS DO PEDIDO
          $order_data = $this->get_order_information($next_order_id);
          // echo "<h2>Dados do pedido a ser cadastrado</h2><br>";
          // var_dump(json_encode($order_data));  // DEBUG

          $dadosVenda = new stdClass;
          //------------PRODUTO--------
          foreach ($order_data->items as $key => $value)
          {
            $dadosVenda->sku_produto = $value->id;
            $dadosVenda->nome_produto = $value->name;
            $dadosVenda->qtd_produto = $value->qty;
            $dadosVenda->preco_especial_produto = $value->special_price;
            $dadosVenda->custo_envio = $value->shipping_cost;
            $dadosVenda->preco_original_produto = $value->original_price;
          }

          //--------------PAGAMENTO---------
          foreach ($order_data->payments as $key => $value)
          {
            $dadosVenda->id_order = $id_pedido;
            $dadosVenda->tipo_pagamento = $value->method;
            $dadosVenda->total_pagar = $value->value;
            $dadosVenda->parcels = $value->parcels;
          }
          $dadosVenda->desconto = $dados_pedido->discount;
          //----- ------ENDEREÇO ENTREGA---------
          $dadosVenda->shipping_receptor = $dados_pedido->shipping_address->full_name;
          $dadosVenda->shipping_rua = $dados_pedido->shipping_address->street;
          $dadosVenda->shipping_numero = $dados_pedido->shipping_address->number;
          $dadosVenda->shipping_bairro = $dados_pedido->shipping_address->neighborhood;
          $dadosVenda->shipping_cep = $dados_pedido->shipping_address->postcode;
          $dadosVenda->shipping_cidade = $dados_pedido->shipping_address->city;
          $dadosVenda->shipping_estado = $dados_pedido->shipping_address->region;
          $dadosVenda->shipping_pais = $dados_pedido->shipping_address->country;
          $dadosVenda->shipping_phone = $dados_pedido->shipping_address->phone;
          $dadosVenda->shipping_referencia = $dados_pedido->shipping_address->reference;
          //----- ------ENDEREÇO COBRANÇA---------
          $dadosVenda->billing_receptor = $dados_pedido->billing_address->full_name;
          $dadosVenda->billing_rua = $dados_pedido->billing_address->street;
          $dadosVenda->billing_numero = $dados_pedido->billing_address->number;
          $dadosVenda->billing_bairro = $dados_pedido->billing_address->neighborhood;
          $dadosVenda->billing_cep = $dados_pedido->billing_address->postcode;
          $dadosVenda->billing_cidade = $dados_pedido->billing_address->city;
          $dadosVenda->billing_estado = $dados_pedido->billing_address->region;
          $dadosVenda->billing_pais = $dados_pedido->billing_address->country;
          $dadosVenda->billing_phone = $dados_pedido->billing_address->phone;
          $dadosVenda->billing_referencia = $dados_pedido->billing_address->reference;
          // -------USUARIO --------
          $dadosVenda->email_comprador = $dados_pedido->customer->email;
          $dadosVenda->telefone_comprador = $dados_pedido->customer->phones;
          $dadosVenda->nascimento = $dados_pedido->customer->date_of_birth;

          $nome_sobrenome = "B2W-".ucwords(strtolower($dados_pedido->customer->name));
          $nome = explode(' ', $nome_sobrenome);
          $sobrenome = array_splice($nome, -1);
          $nome = implode(' ',$nome);
          $sobrenome = implode(' ',$sobrenome);

          $dadosVenda->nome_comprador = $nome;
          $dadosVenda->sobrenome_comprador = $sobrenome;
          $dadosVenda->numero_documento_comprador = $dados_pedido->customer->vat_number;

          $buyer_name = $dadosVenda->nome_comprador;
          // instance class to do a body of email using order data
          $email = new email($dadosVenda);
          $email_message = $email->message();
          var_dump($email_message);   //DEBUG
          //Create label
          $label_data = get_order_label($next_order_id);

          if($label_data == null) $filename = null;
          else {
            $filename = "etiquetas/$next_order_id.pdf";
            file_put_contents($filename,$label_data);
          }
          $error_handling = new log("Novo Pedido SKYHUB", "Id Pedido: $next_order_id<br>", $email_message, "nova compra");
          $error_handling->log_email = true;
          $error_handling->mensagem_email = "Nova compra que entrou no magento";
          $error_handling->log_etiqueta = $filename;
          $error_handling->log_email = true;
          $error_handling->email_novacompra = true;
          $error_handling->dir_file = "log/log.json";
          $error_handling->log_files = true;
          $error_handling->send_log_email();
          $error_handling->execute();

          file_put_contents("include/files/last_created_order.json",json_encode($next_order_id));

          $magento_order = new Magento_order($dadosVenda);
          echo "<h2>1 - Criação do customer</h2>";
          // cria cadastro do comprador no magento
          // se ja for cadastrado apenas recupera o id do comprador
          // cria tbm o cadastro do endereço do comprador no magento
          // se for cadastrado recupera as informações
          $id_customer = $magento_order->magento1_customerCustomerCreate();
          var_dump($id_customer);
          if($id_customer == 0) return false;

          echo "<br/><h2>2 - Criação do endereço do customer</h2>";
          // Apenas cria um array com os dados do comprador
          $customer_address = $magento_order->magento2_customerAddressCreate($id_customer);
          var_dump($customer_address);
          if($customer_address == 0) return false;

          echo "<br/><h2>3 - Criação do carrinho de compras</h2>";
          // cria o carrinho de compras, retorna o id do carrinho
          $id_carrinho = $magento_order->magento3_shoppingCartCreate();
          var_dump($id_carrinho);
          if($id_carrinho == 0) return false;

          echo "<br/><h2>4 - Adicionando os podutos no carrinho</h2>";
          // adiciona os produtos no carrinho
          $add_produto = $magento_order->magento4_shoppingCartProductAdd($id_carrinho);
          if($add_produto == 0) return false;

          echo "<br/><h2>5 - Lista do podutos no carrinho</h2>";
          // lista os produtos no carrinho
          $produtos_carrinho = $magento_order->magento5_shoppingCartProductList($id_carrinho);
          var_dump($produtos_carrinho);
          if($produtos_carrinho === 0) return false;

          echo "<br/><h2>6 - Inicializando o customer (shoppingCartCustomerSet)</h2>";
          //seta o comprador com o carrinho
          $customerSet = $magento_order->magento6_shoppingCartCustomerSet($id_carrinho,$id_customer);
          var_dump($customerSet);
          if($customerSet === 0) return false;

          echo "<br/><h2>7 - Iniciando o endereço do customer no carrinho</h2>";
          //seta o endereço do comprador com o carrinho
          $customerAddressSet = $magento_order->magento7_shoppingCartCustomerAddresses($id_carrinho);
          var_dump($customerAddressSet);
          if($customerAddressSet === 0) return false;

          echo "<br/><h2>8 - Setando o método de entrega</h2>";
          //seta o meio de pagamento com o carrinho
          $customerEntregaSet = $magento_order->magento8_shoppingCartShippingMethod($id_carrinho);
          var_dump($customerEntregaSet);
          if($customerEntregaSet === 0) return false;

          echo "<br/><h2>9 - Setando o método de pagamento</h2>";
          //seta o meio de pagamento com o carrinho
          $customerPagamentoSet = $magento_order->magento9_shoppingCartPaymentMethod($id_carrinho);
          var_dump($customerPagamentoSet);
          if($customerPagamentoSet === 0) return false;

          echo "<br/><h2>7 - Finalização da compra</h2>";
          // Finaliza a compra
          $order = $magento_order->magento10_shoppingCartOrder($id_carrinho);
          var_dump($order);
          if($order == 0) return false;

          if($order != 0)
          {
            $order_list = json_decode(file_get_contents("include/files/list_magento_orders.json"));
            $order_list[] = array('B2W' => $next_order_id);
            $conteudo_arquivo = file_put_contents("include/files/list_magento_orders.json", json_encode($order_list));

            $error_handling = new log("Novo Pedido MAGENTO", "Numero do Pedido MGT: $order", "Comprador: $buyer_name", "nova compra");
            $error_handling->log_email = true;
            $error_handling->mensagem_email = "Nova compra SKYHUB entrou no magento com sucesso";
            $error_handling->log_email = true;
            $error_handling->dir_file = "log/log.json";
            $error_handling->log_files = true;
            $error_handling->send_log_email();
            $error_handling->execute();
          }
          return "Sucesso";
        } else {
          echo "Pedido já cadastrado no Magento<br>";
          file_put_contents("include/files/last_created_order.json",json_encode($next_order_id));
        }
      } else echo "Sem novos pedidos<br>";
    } else echo '<h1>PEDIDO DESATIVADO</h1>';
  }
}

?>
