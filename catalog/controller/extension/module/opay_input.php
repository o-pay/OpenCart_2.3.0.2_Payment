<?php 
if ($ecpay_isCollection === false) {
    if ($payment_method['code'] === 'opay' && $code) {
        if ($code === 'opay') {
            $display = 'width: auto;';
        } else {
            $display = 'display: none;';
        }
?>
<form class="form-horizontal" action="<?php echo $opay_action; ?>" method="POST" id="opay_redirect_form">
    <fieldset>
        <div class="form-group">
            <div class="col-sm-4">
                <select name="opay_choose_payment" id="opay_choose_payment" class="form-control" style="<?php echo $display; ?>">
                    <?php foreach ($opay_payment_methods as $payment_type => $payment_desc) { ?>
                    <option value="<?php echo $payment_type; ?>"><?php echo $payment_desc; ?></option>
                    <?php } ?>
                </select>
            </div>
        </div>
    </fieldset>
</form>
<?php
    }
}
?>

<script>
    if (
        document.getElementById("opay_choose_payment") !== null && 
        typeof document.getElementById("opay_choose_payment") !== "undefined"
    ) {
        var opay_payment_method = document.querySelectorAll("input[name='payment_method']");
        opay_payment_method.forEach(function(index) {
            index.onclick = function() {
                if (index.value !== 'opay') {
                    var paymentChoose = {
                        'opay_choose': 'display: none;',
                        'ecpay_choose': 'width: auto;'
                    };
                } else {
                    var paymentChoose = {
                        'opay_choose': 'width: auto;',
                        'ecpay_choose': 'display: none;'
                    };
                }
                document.getElementById('opay_choose_payment').style = paymentChoose.opay_choose;
                if (
                    document.getElementById("ecpay_choose_payment") !== null && 
                    typeof document.getElementById("ecpay_choose_payment") !== "undefined"
                ) {
                    document.getElementById('ecpay_choose_payment').style = paymentChoose.ecpay_choose;
                }
            }
        });
    }
</script>