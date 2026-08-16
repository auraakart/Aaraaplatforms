<?php include('Crypto.php');

$access_code = CCA_ACCESS_CODE;
$merchant_id = CCA_MERCHANT_ID;

?>

<form name="redirect" action="https://madrasmilk.aaraakart.com/ccavenue/ccavRequestHandler.php" method="POST"   >
   
<input type="hidden" name="order_id" value="1243"> 
<input type="hidden" name="merchant_id" value="<?php echo $merchant_id; ?>"> 
<input type="hidden" name="language" value="EN"> 
<input type="hidden" name="amount" value="1.00">
<input type="hidden" name="currency" value="INR"> 
<input type="hidden" name="redirect_url" value="https://madrasmilk.aaraakart.com/ccavenue/payment_response.php?oid=1243"> 
<input type="hidden" name="cancel_url" value="https://madrasmilk.aaraakart.com/ccavenue/payment_cancel.php?oid=1243"> 
<input type="text" name="billing_name" value="Aswick" class="form-field" Placeholder="Billing Name"> 
<input type="text" name="billing_address" value="112" class="form-field" Placeholder="Billing Address">

<input type="text" name="billing_state" value="Tamilnadu" class="form-field" Placeholder="State"> 
<input type="text" name="billing_zip" value="600125" class="form-field" Placeholder="Zipcode">

<input type="text" name="billing_country" value="India" class="form-field" Placeholder="Country">
<input type="text" name="billing_tel" value="9626772779" class="form-field" Placeholder="Phone">

<input type="text" name="billing_email" value="aa@gmail.com" class="form-field" Placeholder="Email">
<button class="btn-payment" type="submit">Pay Now</button>
 
</form>
<script language='javascript'>
    //document.redirect.submit();
</script>