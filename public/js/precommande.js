/**
 * Ajout et suppression de lignes « groupe sanguin » dans le formulaire de
 * précommande. Sans JavaScript, le formulaire reste utilisable avec la ligne
 * déjà affichée : on ne bloque personne.
 */
(function () {
    'use strict';

    var conteneur = document.getElementById('lignes');
    var bouton = document.getElementById('ajouter-ligne');

    if (!conteneur || !bouton) {
        return;
    }

    function ajouterBoutonRetrait(ligne) {
        var retrait = document.createElement('button');
        retrait.type = 'button';
        retrait.className = 'bouton bouton-texte';
        retrait.textContent = 'Retirer';
        retrait.addEventListener('click', function () {
            ligne.remove();
        });
        ligne.appendChild(retrait);
    }

    Array.prototype.forEach.call(
        conteneur.querySelectorAll('.ligne-poche'),
        function (ligne, index) {
            if (index > 0) {
                ajouterBoutonRetrait(ligne);
            }
        }
    );

    bouton.addEventListener('click', function () {
        var index = parseInt(conteneur.dataset.index, 10);
        var html = conteneur.dataset.prototype.replace(/__name__/g, index);

        var ligne = document.createElement('div');
        ligne.className = 'ligne-poche';
        ligne.innerHTML = html;
        ajouterBoutonRetrait(ligne);

        conteneur.appendChild(ligne);
        conteneur.dataset.index = index + 1;

        var premier = ligne.querySelector('select, input');
        if (premier) {
            premier.focus();
        }
    });
}());
