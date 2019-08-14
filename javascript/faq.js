/**
 * FAQ Module javascript for the frontend.
 */
;(function ($) {
    $(document).ready(function () {
        // assuming only one form in DOM
        var $form = $('.faq__rating'),
            $usefuls = $form.find('input[name="Useful"]'),
            $comment = $form.find('#Comment'),
            $actions = $form.find('.action'),
            $counter = $form.find('.faq__char-counter'),
            existingComment = null;

        var counterLimit = 255;

        $form.on('change', showHideComment);
        $comment.on('keyup paste keydown change', ':input', function (e) {
            var $input = $comment.find(':input'),
                newValue = $input.val();

            if (newValue.length > 255) {
                $input.val(newValue.substring(0, 255));
            }
            showCharCount();
        });

        showHideComment();
        showCharCount();

        function showHideComment() {
            var $useful = $usefuls.filter(':checked');

            if ($useful.val() === 'Y') {
                existingComment = $comment.find(':input').val();
                $comment.find(':input').val('');

                $comment.hide();
                $actions.prop('disabled', false);
            }
            else if ($useful.val() === 'N') {
                if (existingComment) {
                    $comment.find(':input').val(existingComment);
                }
                $comment.show();
                $actions.prop('disabled', false);
            }
            else {
                $comment.hide();
                $actions.prop('disabled', true);
            }
        }

        function showCharCount() {
            var charUsed = $comment.find(':input').val().length;
            $counter.html('You have ' + (counterLimit - charUsed) + ' characters left');
        }
    });
}(jQuery));
