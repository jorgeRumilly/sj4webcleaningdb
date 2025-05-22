{* logs.tpl - affichage des logs du module *}

{if empty($log_files)}
    <div class="panel">
        <div class="alert alert-warning">
            {l s='Aucun fichier de log disponible pour le moment.' d='Modules.Sj4webCleaningDb.Admin'}
        </div>
    </div>
{else}
    <div class="panel">
        <div class="panel-heading">
            <i class="icon-file-text"></i> {l s='Fichiers de log disponibles' d='Modules.Sj4webCleaningDb.Admin'}
        </div>
        <div class="panel-body">
            {if $log_summary && $log_summary|count > 0}
                <table class="table table-bordered">
                    <thead class="thead-light">
                    <tr>
                        <th>{l s='Table' d='Modules.Sj4webCleaningDb.Admin'}</th>
                        <th>{l s='Suppressions' d='Modules.Sj4webCleaningDb.Admin'}</th>
                        <th>{l s='Optimisation' d='Modules.Sj4webCleaningDb.Admin'}</th>
                        <th>{l s='Origine' d='Modules.Sj4webCleaningDb.Admin'}</th>
                        <th>{l s='Avant (Mo)' d='Modules.Sj4webCleaningDb.Admin'}</th>
                        <th>{l s='Après (Mo)' d='Modules.Sj4webCleaningDb.Admin'}</th>
                        <th>{l s='Gain (Mo)' d='Modules.Sj4webCleaningDb.Admin'}</th>
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
                                            <span class="badge badge-secondary bg-secondary">MANUEL</span>
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
                <p class="text-muted">{l s='Aucune opération détectée dans ce fichier de log.' d='Modules.Sj4webCleaningDb.Admin'}</p>
            {/if}
        </div>
    </div>
    <div class="panel">
        <div class="panel-heading">
            <i class="icon-file"></i> {l s='Contenu brut du fichier log' d='Modules.Sj4webCleaningDb.Admin'} : <strong>{$log_date}</strong>
        </div>
        <div class="panel-body">
            <pre style="max-height: 400px; overflow: auto; background: #f9f9f9; border: 1px solid #ccc; padding: 10px;">{$log_content|escape:'htmlall':'UTF-8'}</pre>
        </div>
    </div>
{/if}
