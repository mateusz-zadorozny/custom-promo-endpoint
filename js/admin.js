jQuery(document).ready(function ($) {
    console.log("CUSOM");
    $('#flush_permalinks').on('click', function (e) {
        e.preventDefault();

        $.post(ajax_params.ajax_url, {
            action: "flush_permalinks",
            nonce: ajax_params.nonce
        }, function (response) {
            if (response.success) {
                alert('Permalinks flushed successfully!');
            } else {
                alert('Error flushing permalinks.');
            }
        });
    });
});
