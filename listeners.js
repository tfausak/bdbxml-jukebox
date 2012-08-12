function setOrientation() {
    var orientation = window.orientation;

    switch (orientation) {
        case 0:
            document.body.id = 'portrait';
            break;
        case 90:
        case -90:
            document.body.id = 'landscape';
            break;
        default:
            break;
    }
}

window.onload = function() {
    setOrientation();

    setTimeout('window.scrollTo(0, 1);', 1);
};

window.onorientationchange = setOrientation;
