{extends "$layout"}

{block name="content"}
    <section>
        <p>{l s='There was an issue creating your payment request.'}</p>
        <p>{l s='Reason(s):'}</p>
        <hr>
        <ul>
            {foreach $errors as $error}
            {foreach from=$error key=name item=value}
                <li>{$name}: {$value}</li>
            {/foreach}
                <hr>
            {/foreach}
        </ul>
        <p>{l s="Please advise the merchant of this issue so that it can be resolved as soon as possible."}</p>
    </section>
{/block}