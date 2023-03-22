(function(t) {
    t.toggleRatebox = function($event, $fieldkey) {
        console.log($fieldkey);
        console.log($event);
        console.log($event.value);
        console.log(this.CONTENT_OTHERDATA[$fieldkey]);
//        this.CONTENT_OTHERDATA[$fieldkey] = "4";
    }
})(this);