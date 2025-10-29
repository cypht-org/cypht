function applyBlockListPageHandlers() {
    blockListPageHandlers();
}

function applySieveFiltersPageHandler() {
    sieveFiltersPageHandler();

    return () => {
        cleanUpSieveFiltersPage();
    };
}