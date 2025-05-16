import $ from 'jquery';
import * as bootstrap from 'bootstrap';

$(document).ready(function () {
    // Système de commentaires en AJAX
    const $commentForm = $('#comment-form');
    const $commentsList = $('#comments-list');
    const $commentsCount = $('#comments-count');

    $commentForm.on('submit', function (e) {
        e.preventDefault();

        const $submitBtn = $commentForm.find('button[type="submit"]');
        const originalBtnText = $submitBtn.html();

        // Désactiver le bouton et afficher un indicateur de chargement
        $submitBtn.html('Envoi en cours...').prop('disabled', true);

        $.ajax({
            url: $commentForm.attr('action'),
            method: 'POST',
            data: $commentForm.serialize(),
            dataType: 'json',
            success: function (response) {
                if (response.success) {
                    // Ajouter le nouveau commentaire à la liste
                    $commentsList.prepend(response.commentHtml);

                    // Supprimer le message "Aucun commentaire..." s’il existe
                    $('#no-comments-msg').remove();

                    // Mettre à jour le compteur de commentaires
                    $commentsCount.text(response.commentsCount);

                    // Réinitialiser le formulaire
                    $commentForm[0].reset();

                    // Afficher un message de succès
                    showAlert('success', 'Votre commentaire a été publié avec succès !');
                } else {
                    showAlert('danger', response.error || 'Une erreur est survenue lors de l\'envoi du commentaire.');
                }
            },
            error: function () {
                showAlert('danger', 'Une erreur est survenue lors de l\'envoi du commentaire.');
            },
            complete: function () {
                // Réactiver le bouton
                $submitBtn.html(originalBtnText).prop('disabled', false);
            }
        });
    });

    // Système de "j'aime" en AJAX (via API)
    $('.like-button').on('click', function () {
        const $btn = $(this);
        const articleId = $btn.data('article-id');

        $.ajax({
            url: `/api/article/${articleId}/like`,
            method: 'POST',
            dataType: 'json',
            success: function (response) {
                if (response.success) {
                    $('#likes-count').text(response.likesCount);

                    const $label = $btn.find('.like-label');

                    if (response.liked) {
                        $label.text('💔 Je n’aime plus');
                    } else {
                        $label.text('❤️ J’aime');
                    }
                } else {
                    showAlert('danger', 'Une erreur est survenue lors du like.');
                }
            },
            error: function () {
                showAlert('danger', 'Impossible de traiter votre like.');
            }
        });
    });

    // Fonction pour afficher des alertes
    function showAlert(type, message) {
        const $alert = $(`
            <div class="alert alert-${type} alert-dismissible fade show" role="alert">
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        `);

        $('#alerts-container').append($alert);

        setTimeout(() => {
            const alertInstance = bootstrap.Alert.getOrCreateInstance($alert[0]);
            alertInstance.close();
        }, 5000);
    }
});
