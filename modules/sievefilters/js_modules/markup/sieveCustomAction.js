const sieveCustomActionMarkup = (mailbox) => {
    return `
        <div id="create-filter-form">
            <input type="hidden" id="custom_action_mailbox" value="${mailbox}">
                <div class="modal-body">
                    <div class="filter-section mb-4">
                        <h6 class="fw-bold mb-3">From emails</h6>

                        <div class="mb-3">
                        <div class="btn-group btn-group-sm subject-filter-teal" role="group" id="from-filter-type">
                            <input type="radio" class="btn-check" name="fromFilterType" id="fromMatches" value="matches" checked>
                            <label class="btn btn-outline-primary" for="fromMatches">
                                <i class="bi bi-check-circle me-1"></i> Matches
                            </label>

                            <input type="radio" class="btn-check" name="fromFilterType" id="fromNotMatches" value="not_matches">
                            <label class="btn btn-outline-primary" for="fromNotMatches">
                                <i class="bi bi-x-circle me-1"></i> Does Not Matches
                            </label>
                        </div>
                    </div>

                    <div id="from-keywords-section">
                        <div id="filter-from-list" class="chip-container border rounded p-3 mb-3 bg-light"></div>
                            <div class="input-group">
                                <input id="filter-from-input" class="form-control" placeholder="Add email and press Enter">
                            <div class="input-group-append">
                                <span class="input-group-text"><i class="bi bi-plus-circle"></i></span>
                            </div>
                        </div>
                        <small class="form-text text-muted mt-1">Press Enter to add email to filter</small>
                    </div>
                </div>      
            <hr class="my-4">

            <div class="filter-section mb-4">
                <h6 class="fw-bold mb-3">Subject keywords</h6>

                <!-- Subject filter type selector -->
                <div class="mb-3">
                    <div class="btn-group btn-group-sm subject-filter-teal" role="group" id="subject-filter-type">
                        <input type="radio" class="btn-check" name="subjectFilterType" id="subjectContains" value="contains" checked>
                        <label class="btn btn-outline-primary" for="subjectContains">
                            <i class="bi bi-check-circle me-1"></i> Contains
                        </label>

                        <input type="radio" class="btn-check" name="subjectFilterType" id="subjectNotContains" value="not_contains">
                        <label class="btn btn-outline-primary" for="subjectNotContains">
                            <i class="bi bi-x-circle me-1"></i> Does Not Contain
                        </label>

                        <input type="radio" class="btn-check" name="subjectFilterType" id="subjectAny" value="any">
                        <label class="btn btn-outline-primary" for="subjectAny">
                            <i class="bi bi-slash-circle me-1"></i> Ignore Subject
                        </label>
                    </div>
                </div>

                <div id="subject-keywords-section">
                    <div id="filter-subject-list" class="chip-container border rounded p-3 mb-3 bg-light"></div>
                        <div class="input-group">
                            <input id="filter-subject-input" class="form-control" placeholder="Add keyword and press Enter">
                            <div class="input-group-append">
                                <span class="input-group-text"><i class="bi bi-plus-circle"></i></span>
                            </div>
                        </div>
                        <small class="form-text text-muted mt-1">Press Enter to add keyword to filter</small>
                    </div>
                </div>
            </div>
        </div>
    `;
}

function renderChips(container, values, type = 'email') {
    const $c = $(container).empty();
    const chipClass = type === 'email' ? 'email-chip' : 'keyword-chip';

    values.forEach((val) => {
        const chip = $(`<div class="chip" data-value="${val}">
                <span ${chipClass}">
                    ${val}
                </span>
                <button type="button" class="chip-remove" aria-label="Remove">
                    x
                </button>
            </div>
        `);

        chip.find('.chip-remove').on('click', () => chip.remove());
        $c.append(chip);
    });
}