{* logs.tpl - affichage des logs du module *}

{if empty($log_files)}
    <div class="panel">
        <div class="alert alert-warning">
            {l s='No log file available at the moment.' d='Modules.Sj4webcleaningdb.Admin'}
        </div>
    </div>
{else}
    <div class="panel">
        <div class="panel-heading">
            <i class="icon-file-text"></i> {l s='Available log files' d='Modules.Sj4webcleaningdb.Admin'}
        </div>
        <div class="panel-body">
            {if $log_summary && $log_summary|count > 0}
                <table class="table table-bordered">
                    <thead class="thead-light">
                    <tr>
                        <th>{l s='Table' d='Modules.Sj4webcleaningdb.Admin'}</th>
                        <th>{l s='Deleted rows' d='Modules.Sj4webcleaningdb.Admin'}</th>
                        <th>{l s='Optimization' d='Modules.Sj4webcleaningdb.Admin'}</th>
                        <th>{l s='Origin' d='Modules.Sj4webcleaningdb.Admin'}</th>
                        <th>{l s='Before (MB)' d='Modules.Sj4webcleaningdb.Admin'}</th>
                        <th>{l s='After (MB)' d='Modules.Sj4webcleaningdb.Admin'}</th>
                        <th>{l s='Gain (MB)' d='Modules.Sj4webcleaningdb.Admin'}</th>
                    </tr>
                    </thead>
                    <tbody>
                    {foreach from=$log_summary key=table item=data}
                        <tr>
                            <td><strong>{$table|escape}</strong></td>
                            <td>{if isset($data.delete)}{$data.delete|intval}{else}-{/if}</td>
                            <td>{if isset($data.optimize)}✔️{/if}</td>
                            <td>
                                {if $data.tags}
                                    {foreach $data.tags as $tag}
                                        {if $tag == 'CRON'}
                                            <span class="badge badge-info bg-info">CRON</span>
                                        {elseif $tag == 'BO'}
                                            <span class="badge badge-success bg-success">BO</span>
                                        {elseif $tag == 'MANUEL'}
                                            <span class="badge badge-secondary bg-secondary">MANUAL</span>
                                        {else}
                                            <span class="badge bg-light text-dark">{$tag}</span>
                                        {/if}
                                    {/foreach}
                                {else}
                                    -
                                {/if}
                            </td>
                            <td>{$data.before|default:0|string_format:"%.2f"}</td>
                            <td>{$data.after|default:0|string_format:"%.2f"}</td>
                            <td>
                                {assign var=gain value=$data.before - $data.after}
                                {if $gain > 0}
                                    <span class="bg-success text-success">-{$gain|string_format:"%.2f"}</span>
                                {elseif $gain < 0}
                                    <span class="bg-danger text-danger">+{$gain|string_format:"%.2f"}</span>
                                {else}
                                    0.00
                                {/if}
                            </td>
                        </tr>
                    {/foreach}
                    </tbody>
                </table>
            {else}
                <p class="text-muted">{l s='No operations detected in this log file.' d='Modules.Sj4webcleaningdb.Admin'}</p>
            {/if}
        </div>
    </div>
    <div class="panel">
        <div class="panel-heading">
            <i class="icon-file"></i> {l s='Raw content of the log file' d='Modules.Sj4webcleaningdb.Admin'} : <strong>{$log_date}</strong>
        </div>
        <div class="panel-body">
            <pre class="log-raw">{foreach from=$log_content item=line}{$line|escape:'htmlall'}<br>{/foreach}</pre>
            {*{if $log_raw_lines}<pre class="log-raw">{foreach $log_raw_lines as $line}{$line|escape:'htmlall'}<br>{/foreach}</pre>{/if}*}
            {* <pre class="log-raw">{$log_content|escape:'htmlall'}</pre>*}
        </div>
    </div>
{/if}
