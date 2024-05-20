class AddPDefaultWidget {
    constructor($scope, $) {
        this.$scope = $scope;
        this.$ = $;
        this.init();
    }

    init() {
        const button = this.$scope[0].querySelector('.add-p-default-button');
        const editableContent = this.$scope[0].querySelector('.editable-content');

        button.addEventListener('click', () => {
            //escape html tags
            const text = editableContent.innerText;
            const escapedText = text.replace(/</g, '&lt;').replace(/>/g, '&gt;');
            //push in to text area with id pensierino_comment_id
            const textarea = document.getElementById('pensierino_comment_id');
            textarea.value = textarea.value + escapedText + '\n';
        });
    }
}

jQuery(window).on('elementor/frontend/init', () => {
    elementorFrontend.hooks.addAction('frontend/element_ready/add_p_default_widget.default', ($scope, $) => {
        new AddPDefaultWidget($scope, $);
    });
});
