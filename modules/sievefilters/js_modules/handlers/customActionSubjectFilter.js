function handleCustomActionSubjectFilter() {
    $('.chip-remove')
            .on('click', function () {
                $(this).closest('.chip').remove();
            });

        $('#filter-from-input')
            .on('keydown', function (e) {
                if (e.key === 'Enter' && this.value.trim()) {
                    e.stopPropagation();
                    e.preventDefault();
                    const chip = $(`
        <span class="chip">
          ${this.value.trim()}
          <button type="button" class="chip-remove">×</button>
        </span>
      `);
                    chip.find('.chip-remove').on('click', function () {
                        $(this).closest('.chip').remove();
                    });
                    $('#filter-from-list').append(chip);
                    this.value = '';
                }
            });

        // Similar for filter-subject-input
        $('#filter-subject-input')
            .off('keydown')
            .on('keydown', function (e) {
                if (e.key === 'Enter' && this.value.trim()) {
                    e.stopPropagation();
                    e.preventDefault();
                    const chip = $(`
        <span class="chip">
          ${this.value.trim().toLowerCase()}
          <button type="button" class="chip-remove">×</button>
        </span>
      `);
                    chip.find('.chip-remove').on('click', function () {
                        $(this).closest('.chip').remove();
                    });
                    $('#filter-subject-list').append(chip);
                    this.value = '';
                }
            });

        $("input[name='subjectFilterType']").on(
            'change',
            function () {
                const subjectKeywordsSection = $('#subject-keywords-section');
                const subjectInput = $('#filter-subject-input');
                if ($(this).val() === 'any') {
                    subjectKeywordsSection.hide();
                    subjectInput.prop('disabled', true);
                } else {
                    subjectKeywordsSection.show();
                    subjectInput.prop('disabled', false);

                    const placeholder =
                        $(this).val() === 'contains'
                            ? 'Add keyword and press Enter'
                            : 'Add keyword to exclude and press Enter';
                    subjectInput.attr('placeholder', placeholder);
                }
            },
        );

        $("input[name='fromFilterType']").on('change', function () {
            const fromKeywordsSection = $('#from-keywords-section');
            const fromInput = $('#filter-from-input');
            if ($(this).val() === 'any') {
                fromKeywordsSection.hide();
                fromInput.prop('disabled', true);
            } else {
                fromKeywordsSection.show();
                fromInput.prop('disabled', false);

                const placeholder =
                    $(this).val() === 'matches'
                        ? 'Add keyword and press Enter'
                        : 'Add keyword to exclude and press Enter';
                fromInput.attr('placeholder', placeholder);
            }
        });
}