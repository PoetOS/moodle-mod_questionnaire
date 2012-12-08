/*
function set_item_focus(itemid) {
    var item = document.getElementById(itemid);
    if(item){
        item.focus();
    }
}
*/

M.mod_questionnaire = {};

M.mod_questionnaire.init_sendmessage = function(Y) {
    Y.on('click', function(e) {
        Y.all('input.usercheckbox').each(function() {
            this.set('checked', 'checked');
        });
    }, '#checkall');

    Y.on('click', function(e) {
        Y.all('input.usercheckbox').each(function() {
            this.set('checked', '');
        });
    }, '#checknone');

    Y.on('click', function(e) {
        Y.all('input.usercheckbox').each(function() {
            if (this.get('alt') == 0) {
                this.set('checked', 'checked');
            } else {
            	this.set('checked', '');
            }
        });
    }, '#checknotstarted');

    Y.on('click', function(e) {
        Y.all('input.usercheckbox').each(function() {
            if (this.get('alt') == 1) {
                this.set('checked', 'checked');
            } else {
            	this.set('checked', '');
            }
        });
    }, '#checkstarted');

};
