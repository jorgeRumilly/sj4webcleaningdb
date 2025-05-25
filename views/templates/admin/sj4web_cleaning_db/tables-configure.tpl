<div class="panel">
    <h3 class="panel-heading">{l s='Table-specific cleanup' d='Modules.Sj4webcleaningdb.Admin'}</h3>
    <div class="row">
        {foreach from=$tables_config key=table item=info name=tableLoop}
        <div class="col-md-6">
            <div class="form-group">
                <label class="form-control-label d-block">
                    <input type="checkbox" name="enabled_tables[{$table}]" value="1" class="me-1"
                           {if isset($enabled_tables[$table])}checked{/if} />
                    {l s='Enable cleanup for table "%s"' sprintf=[$info.label] d='Modules.Sj4webcleaningdb.Admin'}
                </label>

                {if $info.clean_type === 'date'}
                    <div class="mt-2">
                        <label>
                            {l s='Keep data for' d='Modules.Sj4webcleaningdb.Admin'}
                            <input type="number"
                                   name="retention_days[{$table}]"
                                   class="form-control mt-1"
                                   min="1"
                                   value="{if isset($retention_values[$table])}{$retention_values[$table]}{else}90{/if}" />
                            <small class="text-muted">{l s='days' d='Modules.Sj4webcleaningdb.Admin'}</small>
                        </label>
                    </div>
                {/if}
            </div>
        </div>

        {if $smarty.foreach.tableLoop.iteration % 2 == 0}
    </div><div class="row">
        {/if}
        {/foreach}
    </div>
</div>
