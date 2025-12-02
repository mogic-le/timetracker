Ext.define('Netresearch.store.AdminHolidays', {
    extend: 'Ext.data.Store',

    requires: [
        'Netresearch.model.Holiday'
    ],

    autoLoad: false,
    model: 'Netresearch.model.Holiday',
    proxy: {
        type: 'ajax',
        url: url + 'getAllHolidays',
        reader: {
            type: 'json',
            record: 'holiday'
        }
    }
});

