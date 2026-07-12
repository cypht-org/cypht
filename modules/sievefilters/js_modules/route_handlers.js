function applyBlockListPageHandlers() {
    blockListPageHandlers();
}

function applySieveFiltersPageHandler() {
    sieveFiltersPageHandler();

    handleSieveCustomAction();

    return () => {
        cleanUpSieveFiltersPage();
    };
}