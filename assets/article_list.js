import $ from 'jquery';
import 'datatables.net';
import 'datatables.net-bs5';
import 'datatables.net-responsive-bs5';

$(document).ready(function () {
    // Initialisation de DataTables
    const articlesTable = $('#articles-table').DataTable({
        processing: true,
        serverSide: true,
        responsive: true,
        ajax: {
            url: '/api/article/list',
            type: 'GET'
        },
        columns: [
            { data: 'id' },
            { data: 'title' },
            { data: 'categories' },
            { data: 'commentsCount' },
            { data: 'likesCount' },
            { data: 'createdAt' },
            { data: 'actions', orderable: false, searchable: false }
        ],
        language: {
            url: 'https://cdn.datatables.net/plug-ins/1.13.4/i18n/fr-FR.json'
        },
        order: [[0, 'desc']]
    });

    // Recherche en temps réel
    const $searchInput = $('#search-article');
    const $searchResults = $('#search-results');
    let searchTimeout;

    $searchInput.on('input', function () {
        const query = $(this).val().trim();
        clearTimeout(searchTimeout);

        if (query.length < 2) {
            $searchResults.removeClass('show').html('');
            return;
        }

        searchTimeout = setTimeout(() => {
            $.ajax({
                url: '/api/article/search',
                method: 'GET',
                data: { q: query },
                dataType: 'json',
                success: function (response) {
                    if (response.results && response.results.length > 0) {
                        let html = '';
                        response.results.forEach(article => {
                            html += `
                                <div class="search-item" data-id="${article.id}">
                                    <strong>${article.title}</strong><br>
                                    <small>${article.categories.join(', ')}</small>
                                </div>
                            `;
                        });
                        $searchResults.html(html).addClass('show');
                    } else {
                        $searchResults.html('<div class="text-muted">Aucun résultat trouvé</div>').addClass('show');
                    }
                }
            });
        }, 300);
    });

    // Cliquer sur un résultat
    $(document).on('click', '.search-item', function () {
        const articleId = $(this).data('id');
        if (articleId) {
            window.location.href = `/article/${articleId}`;
        }
    });

    // Cacher les résultats si on clique ailleurs
    $(document).on('click', function (e) {
        if (!$(e.target).closest('.search-container').length) {
            $searchResults.removeClass('show');
        }
    });
});
