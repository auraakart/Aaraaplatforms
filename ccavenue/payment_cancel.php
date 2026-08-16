<?php

include('Crypto.php');

//var_dump($_REQUEST);



$workingKey = CCA_WORKING_KEY; //Working Key.
$encResponse = $_POST["encResp"];     //This is the response sent by the CCAvenue Server
 
$rcvdString = decrypt($encResponse,$workingKey);  //Crypto Decryption used as per the specified working key.
$order_status="";
$decryptValues=explode('&', $rcvdString);
$dataSize=sizeof($decryptValues);

for($i = 0; $i < $dataSize; $i++) {
    $information=explode('=',$decryptValues[$i]);
    if($i==3){ 
        $order_status = $information[1];
    }
    //var_dump($information);
    $json = json_encode($information, JSON_PRETTY_PRINT);
    echo $json;
}
?>