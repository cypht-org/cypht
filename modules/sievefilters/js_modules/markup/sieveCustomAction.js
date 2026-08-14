const sieveCustomActionMarkup = (mailbox) => {
    return `
        <div id="create-filter-form">
            <input type="hidden" id="custom_action_mailbox" value="${mailbox}">
            <div class="modal-body">
                <div class="sieve-filter-name-group">
                    <label>${hm_trans('Action Name')}</label>
                    <input type="text" class="manual_action_name_input form-control" placeholder="${hm_trans('e.g., Move to Spam')}">
                </div>
                <small class="text-muted d-block mt-2">${hm_trans('After clicking "Configure Actions", you will select what this custom action does.')}</small>
            </div>
        </div>
    `;
}
