Ext.define('Netresearch.model.Holiday', {
    extend: 'Ext.data.Model',
    fields: [
        {name: 'day', type: 'string'},
        {name: 'name', type: 'string'}
    ],
    idProperty: 'day'
});

