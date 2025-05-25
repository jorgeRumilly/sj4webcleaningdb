{if $table_stats}
    <div class="panel">
        <div class="panel-heading">
            <i class="icon-list-alt"></i> {l s='Current status of cleaned tables' d='Modules.Sj4webcleaningdb.Admin'}
        </div>
        <div class="panel-body">
            <table class="table">
                <thead>
                <tr>
                    <th>{l s='Table' d='Modules.Sj4webcleaningdb.Admin'}</th>
                    <th>{l s='Number of rows' d='Modules.Sj4webcleaningdb.Admin'}</th>
                    <th>{l s='Size (MB)' d='Modules.Sj4webcleaningdb.Admin'}</th>
                </tr>
                </thead>
                <tbody>
                {foreach from=$table_stats key=table item=stat}
                    <tr>
                        <td>{$table}</td>
                        <td>{$stat.rows|number_format:0}</td>
                        <td>{$stat.size|string_format:"%.2f"} MB</td>
                    </tr>
                {/foreach}
                </tbody>
            </table>
        </div>
    </div>
{/if}
