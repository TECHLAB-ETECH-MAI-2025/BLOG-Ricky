document.addEventListener('DOMContentLoaded', function () {
    const currentUserId = window.CURRENT_USER_ID;

    // Récupère un token Mercure et l'URL du flux d'événements pour l'utilisateur courant
    fetch(`/api/chat/notifications-stream?userId=${currentUserId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Configure le cookie pour Mercure avec le token reçu
                const url = new URL(data.url);
                document.cookie = 'mercureAuthorization=' + data.token + '; path=/.well-known/mercure; samesite=strict';

                // Ouvre une connexion EventSource pour écouter les notifications en temps réel
                const eventSource = new EventSource(url.toString(), { withCredentials: true });

                eventSource.onmessage = function (event) {
                    // Met à jour les badges de messages non lus à chaque notification reçue
                    const eventData = JSON.parse(event.data);
                    updateUnreadBadges(eventData.counts);
                };

                eventSource.onerror = function (err) {
                    console.error('Erreur EventSource:', err);
                    eventSource.close(); // Ferme proprement la connexion en cas d'erreur
                };
            } else {
                console.error('Erreur récupération token Mercure:', data);
            }
        });

    // Requête initiale pour afficher les badges de messages non lus au chargement
    fetch(`/api/chat/unread-counts`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                updateUnreadBadges(data.counts);
            }
        });
});

// Met à jour dynamiquement les badges de messages non lus dans l'interface
function updateUnreadBadges(counts) {
    counts.forEach(item => {
        const badge = document.getElementById(`unread-${item.senderId}`);
        if (badge) {
            if (item.unreadCount > 0) {
                badge.innerHTML = `<i class="bi bi-envelope-fill me-1"></i>${item.unreadCount}`;
                badge.style.display = 'inline-flex';
            } else {
                badge.style.display = 'none';
                badge.textContent = '';
            }
        }
    });
}
