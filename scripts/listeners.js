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

    var id = this.href.match(/remove=(\d+)/);
    id = id[1];
    var song = document.getElementById('song-' + id);

    song.parentNode.removeChild(song);

    var songs = document.getElementsByTagName('li'), li;

    for (var i = 0, length = songs.length; i < length; i++) {
        link = songs[i];

        var tmp_id = this.id.match(/song-(\d+)/);
        tmp_id = tmp_id[1];

        alert(tmp_id);
    }

    return false;
}

function up() {
    var request = new XMLHttpRequest();
    request.open('get', this.href);
    request.send(null);

    this.innerHTML = '&#x2713;';
    this.href = "#";

    return false;
}

function down() {
    var request = new XMLHttpRequest();
    request.open('get', this.href);
    request.send(null);

    this.innerHTML = '&#x2713;';
    this.href = "#";

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

    //setTimeout('window.scrollTo(0, 1);', 1);

    var links = document.getElementsByTagName('a'), a;

    for (var i = 0, length = links.length; i < length; i++) {
        link = links[i];
        link_class = link.className.toLowerCase();

        if (link_class.indexOf('add') !== -1) {
            link.onclick = add;
        }
        else if (link_class.indexOf('remove') !== -1) {
            link.onclick = remove;
        }
        else if (link_class.indexOf('up') !== -1) {
            link.onclick = up;
        }
        else if (link_class.indexOf('down') !== -1) {
            link.onclick = down;
        }
        else if (link_class.indexOf('clear') !== -1) {
            link.onclick = clear;
        }
    }
};

window.onorientationchange = setOrientation;
