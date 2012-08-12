function add() {
    var request = new XMLHttpRequest();
    request.open('get', this.href);
    request.send(null);

    this.innerHTML = '&#x2713;';
    this.href = "#";

    return false;
}

function remove() {
    var request = new XMLHttpRequest();
    request.open('get', this.href);
    request.send(null);

    this.parentNode.innerHTML = '';

    return false;
}

function clear() {
    var request = new XMLHttpRequest();
    request.open('get', this.href);
    request.send(null);

    setTimeout('location.reload(true);', 1);

    return false;
}

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

    var links = document.getElementsByTagName('a'), a;

    for (var i = 0, length = links.length; i < length; i++) {
        link = links[i];
        link_class = link.className.toLowerCase();

        if (link_class.indexOf('add') !== -1) {
            link.onclick = add;
        } else if (link_class.indexOf('remove') !== -1) {
            link.onclick = remove;
        } else if (link_class.indexOf('clear') !== -1) {
            link.onclick = clear;
        }
    }
};

window.onorientationchange = setOrientation;
