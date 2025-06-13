import $ from 'jquery';
import 'bootstrap';

$(document).ready(function () {
    const userId = parseInt($('#current-user-id').val());
    const receiverId = parseInt($('#receiver-id').val());

    const $messagesContainer = $('#messages-container');
    const $messageInput = $('#message_form_content');
    const $sendButton = $('#send-message');
    const $connectionBadge = $('#connection-badge');

    let eventSource = null;
    let reconnectTimeout = null;

    // Met à jour le badge d’état de la connexion Mercure
    function updateConnectionBadge(type, message) {
        $connectionBadge.removeClass('bg-secondary bg-success bg-danger bg-info');
        switch (type) {
            case 'connected':
                $connectionBadge.addClass('bg-success').text(message || 'Connecté à Mercure');
                break;
            case 'connecting':
                $connectionBadge.addClass('bg-info').text(message || 'Connexion à Mercure...');
                break;
            case 'error':
                $connectionBadge.addClass('bg-danger').text(message || 'Déconnecté de Mercure');
                break;
            default:
                $connectionBadge.addClass('bg-secondary').text(message || 'État inconnu');
        }
    }

    // Affiche une notification bootstrap temporaire
    function showNotification(type, message) {
        const alertType = {
            success: 'alert-success',
            error: 'alert-danger',
            info: 'alert-info'
        }[type] || 'alert-info';

        const $alert = $(`
            <div class="alert ${alertType} alert-dismissible fade show" role="alert">
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fermer"></button>
            </div>
        `);

        $('#notification-area').append($alert);

        setTimeout(() => {
            $alert.alert('close');
        }, 5000);
    }

    // Gestion de l’envoi du message via AJAX
    $sendButton.click(function (e) {
        e.preventDefault();
        const content = $messageInput.val().trim();
        if (!content) {
            showNotification('error', 'Le message ne peut pas être vide.');
            return;
        }

        $sendButton.prop('disabled', true);
        updateConnectionBadge('connecting', 'Envoi en cours...');

        $.ajax({
            url: '/api/chat/send',
            method: 'POST',
            data: {
                content: content,
                receiver: receiverId
            },
            success: function (response) {
                if (response.success) {
                    $messageInput.val('');
                    updateConnectionBadge('connected', 'Connecté à Mercure');
                } else {
                    const msg = response.error || 'Erreur inconnue';
                    showNotification('error', msg);
                    updateConnectionBadge('error', 'Erreur Mercure');
                }
            },
            error: function (jqXHR) {
                let errMsg = 'Erreur lors de l\'envoi du message';
                if (jqXHR.responseJSON && jqXHR.responseJSON.error) {
                    errMsg = 'Erreur : ' + jqXHR.responseJSON.error;
                }
                showNotification('error', errMsg);
                updateConnectionBadge('error', 'Erreur Mercure');
            },
            complete: function () {
                $sendButton.prop('disabled', false);
            }
        });
    });

    // Décode un JWT pour en extraire la date d’expiration
    function decodeJWT(token) {
        try {
            const payload = token.split('.')[1];
            const base64 = payload.replace(/-/g, '+').replace(/_/g, '/');
            const jsonPayload = decodeURIComponent(atob(base64).split('').map(c =>
                '%' + ('00' + c.charCodeAt(0).toString(16)).slice(-2)
            ).join(''));
            return JSON.parse(jsonPayload);
        } catch (e) {
            console.error("Erreur de décodage du JWT :", e);
            return null;
        }
    }

    // Programme la reconnexion automatique à Mercure avant expiration du token
    function scheduleReconnect(expirationUnix) {
        const now = Math.floor(Date.now() / 1000);
        const expiresIn = expirationUnix - now;
        const reconnectIn = Math.max(expiresIn - 60, 10) * 1000;

        if (reconnectTimeout) clearTimeout(reconnectTimeout);
        reconnectTimeout = setTimeout(fetchTokenAndConnect, reconnectIn);
    }

    // Met à jour le badge de messages non lus
    function updateUnreadBadge(senderId, counts) {
        const badge = $(`#unread-${senderId}`);
        if (!badge.length) return;

        const count = counts.find(c => c.senderId == senderId)?.unreadCount || 0;

        if (count > 0) {
            badge.html(`<i class="bi bi-envelope-fill me-1"></i>${count}`);
            badge.css('display', 'inline-flex');
        } else {
            badge.hide().empty();
        }
    }

    // Crée l’EventSource pour les notifications utilisateurs
    function setupNotificationEventSource(token) {
        const notificationTopic = `user/${receiverId}/notifications`;
        const url = new URL(window.MERCURE_PUBLIC_URL);
        url.searchParams.append('topic', notificationTopic);

        document.cookie = `mercureAuthorization=${token}; path=/.well-known/mercure; samesite=strict`;

        const notificationEventSource = new EventSource(url.toString(), { withCredentials: true });

        notificationEventSource.onmessage = event => {
            try {
                const data = JSON.parse(event.data);
                if (data.type === 'unread_update') {
                    updateUnreadBadge(data.senderId, data.counts);
                }
            } catch (e) {
                console.error('Erreur traitement notification Mercure:', e);
            }
        };

        notificationEventSource.onerror = err => {
            console.error('Erreur EventSource notifications:', err);
            notificationEventSource.close();
        };
    }

    // Crée l’EventSource pour les messages de chat
    function setupChatEventSource(token) {
        const topic = `chat/${Math.min(userId, receiverId)}/${Math.max(userId, receiverId)}`;
        const url = new URL(window.MERCURE_PUBLIC_URL);
        url.searchParams.append('topic', topic);

        document.cookie = `mercureAuthorization=${token}; path=/.well-known/mercure; samesite=strict`;

        if (eventSource) eventSource.close();

        eventSource = new EventSource(url.toString(), { withCredentials: true });

        eventSource.onopen = () => {
            updateConnectionBadge('connected', 'Connecté à Mercure');
        };

        eventSource.onerror = () => {
            updateConnectionBadge('error', 'Déconnecté de Mercure');
            showNotification('error', 'Connexion Mercure perdue. Reconnexion...');
            eventSource.close();
            setTimeout(fetchTokenAndConnect, 3000);
        };

        // Affichage du message reçu en temps réel
        eventSource.onmessage = event => {
            try {
                const data = JSON.parse(event.data);
                const msg = data.message;
                if (!msg) return;

                $('#no-messages-placeholder').remove();

                const isCurrentUser = msg.userId === userId;

                if (!isCurrentUser) {
                    $.post('/api/chat/mark-as-read', {
                        senderId: msg.userId
                    });
                }

                const messageElement = $('<div></div>')
                    .addClass('d-flex mb-1')
                    .addClass(isCurrentUser ? 'justify-content-end' : 'justify-content-start');

                const timeElement = $('<div></div>')
                    .addClass('small text-muted me-2')
                    .css({ 'min-width': '40px', 'text-align': 'center' })
                    .text(new Date(msg.createdAt).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }));

                const contentElement = $('<div></div>')
                    .addClass('p-2 rounded-3')
                    .addClass(isCurrentUser ? 'bg-primary text-white' : 'bg-secondary bg-opacity-25 text-dark')
                    .css('max-width', '75%')
                    .text(msg.content);

                messageElement.append(timeElement, contentElement);
                $messagesContainer.append(messageElement);
                $messagesContainer.scrollTop($messagesContainer[0].scrollHeight);
            } catch (e) {
                console.error('Erreur traitement message Mercure:', e);
            }
        };
    }

    // Lance la connexion à Mercure avec le token JWT décodé
    function connectWithToken(token) {
        const decoded = decodeJWT(token);
        if (!decoded || !decoded.exp) {
            updateConnectionBadge('error', 'Token invalide');
            showNotification('error', 'Token Mercure invalide.');
            return;
        }

        scheduleReconnect(decoded.exp);
        setupNotificationEventSource(token);
        setupChatEventSource(token);
    }

    // Récupère le token JWT côté serveur pour se connecter à Mercure
    function fetchTokenAndConnect() {
        updateConnectionBadge('connecting', 'Connexion à Mercure...');
        $.get(`/api/chat/mercure-token?partner=${receiverId}`, function (response) {
            if (response.success && response.token) {
                connectWithToken(response.token);
            } else {
                const message = response.error || 'Impossible d\'obtenir un token Mercure.';
                updateConnectionBadge('error', 'Erreur Mercure');
                showNotification('error', message);
            }
        }).fail(function (jqXHR) {
            let errorMessage = 'Erreur lors de la récupération du token Mercure.';
            if (jqXHR.responseJSON?.error) {
                errorMessage = `Erreur : ${jqXHR.responseJSON.error}`;
            } else if (jqXHR.status) {
                errorMessage = `Erreur ${jqXHR.status} : ${jqXHR.statusText}`;
            }

            updateConnectionBadge('error', 'Erreur Mercure');
            showNotification('error', errorMessage);
        });
    }

    // Initialisation de la connexion Mercure au chargement de la page
    fetchTokenAndConnect();
});
