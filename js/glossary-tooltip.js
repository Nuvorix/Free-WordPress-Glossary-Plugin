jQuery(document).ready(function($) {
    $('body').append(`
        <div class="glossary-overlay"></div>
        <div class="glossary-modal">
            <div class="glossary-modal-header">
                <h2>Glossary Term</h2>
                <span class="glossary-close">X</span>
            </div>
            <div class="glossary-modal-content"></div>
            <div class="glossary-read-more">
                <a href="#" target="_blank" rel="noopener noreferrer">Read More</a>
                <span class="new-tab-note">(Opens in a new tab)</span>
            </div>
        </div>
    `);

    $(document).on('click', '.glossary-term', function(event) {
        event.preventDefault();

        let tooltipText = $(this).data('tooltip-text');
        let link = $(this).data('link');

        // Set the modal title and content safely
        $('.glossary-modal h2').text($(this).text());
        $('.glossary-modal-content').text(tooltipText || 'No additional information available.');
        
        // Set the 'Read More' link safely
        $('.glossary-read-more a').attr('href', link || '#').attr('rel', 'noopener noreferrer');

        $('.glossary-overlay, .glossary-modal').fadeIn();
    });

    $('.glossary-close, .glossary-overlay').click(function() {
        $('.glossary-overlay, .glossary-modal').fadeOut();
    });

    $(document).on('keydown', function(event) {
        if (event.key === "Escape") {
            $('.glossary-overlay, .glossary-modal').fadeOut();
        }
    });
});
