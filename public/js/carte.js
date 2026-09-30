/**
 * Carte des banques de sang (Leaflet servi en local).
 *
 * Principes retenus :
 *  - la liste HTML est rendue par le serveur : si le JavaScript ou les tuiles ne
 *    chargent pas, l'utilisateur voit quand même les banques et leurs stocks ;
 *  - un seul appel réseau par recherche, vers /api/banques ;
 *  - les marqueurs reprennent les visuels de la charte (un par état de stock).
 */
(function () {
    'use strict';

    var conteneur = document.getElementById('carte');
    if (!conteneur || typeof L === 'undefined') {
        return;
    }

    var centre = [
        parseFloat(conteneur.dataset.centreLat),
        parseFloat(conteneur.dataset.centreLon)
    ];

    var carte = L.map(conteneur, { scrollWheelZoom: false }).setView(centre, 12);

    L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 18,
        attribution: '© OpenStreetMap'
    }).addTo(carte);

    var ICONES = {
        DISPONIBLE: 'marqueur-carte.svg',
        TENSION: 'marqueur-carte-tension.svg',
        RUPTURE: 'marqueur-carte-rupture.svg',
        NON_COMMUNIQUE: 'marqueur-carte-rupture.svg'
    };

    var LIBELLES = {
        DISPONIBLE: 'Disponible',
        TENSION: 'En tension',
        RUPTURE: 'Rupture',
        NON_COMMUNIQUE: 'Non communiqué'
    };

    var baseImages = conteneur.dataset.images || '/images/';
    var urlApi = conteneur.dataset.api || '/api/banques';

    function icone(etat) {
        return L.icon({
            iconUrl: baseImages + (ICONES[etat] || ICONES.NON_COMMUNIQUE),
            iconSize: [34, 40],
            iconAnchor: [17, 40],
            popupAnchor: [0, -36]
        });
    }

    var couche = L.layerGroup().addTo(carte);

    function dessiner(banques) {
        couche.clearLayers();

        if (!banques.length) {
            return;
        }

        var limites = [];

        banques.forEach(function (banque) {
            var marqueur = L.marker([banque.latitude, banque.longitude], {
                icon: icone(banque.etat),
                title: banque.nom
            });

            marqueur.bindPopup(
                '<strong>' + banque.nom + '</strong><br>' +
                banque.ville + ' · ' + banque.distance + ' km<br>' +
                '<span class="pastille">' + (LIBELLES[banque.etat] || banque.etat) +
                '</span> ' + banque.quantite + ' poche(s)<br>' +
                '<a href="' + banque.url + '">Voir la fiche et précommander</a>'
            );

            marqueur.addTo(couche);
            limites.push([banque.latitude, banque.longitude]);
        });

        carte.fitBounds(limites, { padding: [40, 40], maxZoom: 14 });
    }

    function charger(parametres) {
        var url = new URL(urlApi, window.location.origin);

        Object.keys(parametres).forEach(function (cle) {
            if (parametres[cle] !== null && parametres[cle] !== '') {
                url.searchParams.set(cle, parametres[cle]);
            }
        });

        fetch(url)
            .then(function (reponse) { return reponse.json(); })
            .then(function (donnees) { dessiner(donnees.banques || []); })
            .catch(function () {
                // Silence volontaire : la liste serveur reste affichée.
            });
    }

    var parametresUrl = new URLSearchParams(window.location.search);

    charger({
        groupe: conteneur.dataset.groupe,
        lat: parametresUrl.get('lat'),
        lon: parametresUrl.get('lon'),
        rayon: parametresUrl.get('rayon')
    });

    // « Utiliser ma position » : on remplit les champs cachés et on relance la
    // recherche serveur, pour que la liste et la carte disent la même chose.
    var bouton = document.getElementById('ma-position');

    if (bouton && navigator.geolocation) {
        bouton.addEventListener('click', function () {
            bouton.disabled = true;
            bouton.textContent = 'Localisation…';

            navigator.geolocation.getCurrentPosition(
                function (position) {
                    document.getElementById('lat').value = position.coords.latitude.toFixed(6);
                    document.getElementById('lon').value = position.coords.longitude.toFixed(6);
                    bouton.form.submit();
                },
                function () {
                    bouton.disabled = false;
                    bouton.textContent = 'Position indisponible';
                },
                { enableHighAccuracy: true, timeout: 10000 }
            );
        });
    } else if (bouton) {
        bouton.hidden = true;
    }
}());
