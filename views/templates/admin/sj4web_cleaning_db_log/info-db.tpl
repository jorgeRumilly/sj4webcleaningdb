{if $table_stats}
    <div class="panel">
        <div class="panel-heading">
            <i class="icon-list-alt"></i> {l s='État actuel des tables traitées' d='Modules.Sj4webCleaningDb.Admin'}
        </div>
        <div class="panel-body">
            <table class="table">
                <thead>
                <tr>
                    <th>Table</th>
                    <th>Nombre de lignes</th>
                    <th>Taille (Mo)</th>
                </tr>
                </thead>
                <tbody>
                {foreach from=$table_stats key=table item=stat}
                    <tr>
                        <td>{$table}</td>
                        <td>{$stat.rows|number_format:0}</td>
                        <td>{$stat.size|string_format:"%.2f"} Mo</td>
                    </tr>
                {/foreach}
                </tbody>
            </table>
        </div>
    </div>
{/if}
