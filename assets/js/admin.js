jQuery(document).ready(function($){
    $('.cfdb7-toggle-paid').on('click', function(){
        var $el = $(this);
        var id = $el.data('id');
        $el.css('opacity', '0.5');
        $.post(ajaxurl, {
            action: 'cfdb7_toggle_paid',
            id: id
        }, function(resp){
            $el.css('opacity', '1');
            if(resp.success) {
                if(resp.data.paid) {
                    $el.html('&#10004;');
                    $el.closest('td').removeClass('cfdb7-paid-no').addClass('cfdb7-paid-yes');
                } else {
                    $el.html('&#10008;');
                    $el.closest('td').removeClass('cfdb7-paid-yes').addClass('cfdb7-paid-no');
                }
            } else {
                alert('Fehler: ' + resp.data);
            }
        });
    });
    $('#cfdb7-select-all').on('change', function(){
        $('input[name="bulk_ids[]"]').prop('checked', $(this).prop('checked'));
    });
});