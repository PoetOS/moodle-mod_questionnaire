(function(){
    /**
     * Questionnaire init function.
     *
     * @param {this} outerThis Window document.
     */
    window.questionnaireInit = function(outerThis) {
        /**
         * Fired when the component routing to has finished animating.
         */
        outerThis.ionViewDidEnter = function() {
            outerThis.init();
        };

        /**
         * Fired when the component loaded from the template.
         */
        outerThis.init = function() {
            const groups = document.querySelectorAll('ion-list ion-reorder-group');
            if (groups) {
                groups.forEach((group) => {
                    const qId = group.getAttribute('data-qid');
                    const qInput = document.getElementById('question-' + qId);
                    // Review the sorting questionnaire.
                    if (qInput.value) {
                        const response = qInput.value.split(',');
                        const reOrderGroups = outerThis.reOrederSortGroups(group.children, response);
                        if (reOrderGroups) {
                            reOrderGroups.forEach(item => group.appendChild(item));
                        }
                    }
                    // Binding event 'ionItemReorder' to the 'ion-reorder-group' element for enable 'drag and drop' feature.
                    group.addEventListener('ionItemReorder', function(e){
                        e.detail.complete(true);
                        outerThis.setValueSorting(qId, group.children);
                    });
                });
            }
        };

        /**
         * Sorting the element in the groups question sort.
         *
         * @param {Array} itemGroups list of children group element.
         * @param {Array} response list reponse of user.
         * @returns the list reordered based on the response of user.
         */
        outerThis.reOrederSortGroups = function(itemGroups, response) {
            return Array.from(response).map((index) => {
               return Array.from(itemGroups).find(item => item.getAttribute('data-index') === index);
            });
        };

        /**
         * Set value for the input field.
         *
         * @param {Number} qId question id.
         * @param {Array} items list of the element which re-order by user.
         */
        outerThis.setValueSorting = function(qId, items) {
            const sorted = Array.from(items).map((item) => item.getAttribute('data-index'));
            const qInput = document.getElementById('question-' + qId);
            qInput.value = sorted.join(',');
        };

        /**
         * Initializing the functions.
         */
        setTimeout(function() {
            outerThis.init();
        });
    };
})();
