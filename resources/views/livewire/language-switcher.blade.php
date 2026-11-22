<span class="lang-switch">
    <button type="button" wire:click="switchTo('bn')" class="{{ app()->getLocale() === 'bn' ? 'active' : '' }}">বাংলা</button>
    <span>·</span>
    <button type="button" wire:click="switchTo('en')" class="{{ app()->getLocale() === 'en' ? 'active' : '' }}">English</button>
</span>
