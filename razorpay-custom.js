jQuery(document).ready(function($) {
    $('#payButton').click(function(e) {
        e.preventDefault();

        var name = $('#name').val();
        var amount = $('#amount').val() * 100; 

        var options = {
            "key": ajax_object.razorpay_key, 
            "amount": amount,
            "currency": "INR",
            "name": name,
            "description": "Test Transaction",
            "handler": function (response){
                $.ajax({
                    type: 'POST',
                    url: ajax_object.ajax_url, 
                    data: {
                        action: 'razorpay_payment_success',
                        payment_id: response.razorpay_payment_id,
                        name: name,
                        amount: amount / 100
                    },
                    success: function(response) {
                        console.log(response);
                        Swal.fire({
                            title: 'Payment Successful',
                            text: 'Payment ID: ' + response.data,
                            icon: 'success',
                            confirmButtonText: 'OK'
                        });
                        $('#razorpayPaymentForm')[0].reset();
                    }
                });
            },
            "prefill": {
                "name": name,
                "email": "",
                "contact": ""
            },
            "theme": {
                "color": "#F37254"
            }
        };
        var rzp1 = new Razorpay(options);
        rzp1.open();
    });
});
