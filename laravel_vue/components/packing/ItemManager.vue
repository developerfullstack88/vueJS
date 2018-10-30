<template>
<table class="table table-striped">
    <thead>
        <tr>
            <td>Lot</td>
            <td>Description</td>
            <td>Bid</td>
            <td>Dimensions</td>
            <td>Fragility</td>
        </tr>
    </thead>
    <tbody>
        <tr v-for="item in unpackedItems">
            <td>{{ item.lot }}</td>
            <td>{{ item.description }}</td>
            <td>{{ item.bid_amount }}</td>
            <td>{{ item.width }} x {{ item.height }} x {{ item.length }}</td>
            <td>
                <select class="form-control" v-model="item.fragility" @change="saveFragility(item)">
                    <option :value="null" disabled>Select Fragilitiy</option>
                    <option>Standard</option>
                    <option>Semi-Fragile</option>
                    <option>Fragile</option>
                    <option>Very Fragile</option>
                    <option>Delicate</option>
                </select>
            </td>
            <td>
                <span v-if="saving[item.id]" class="glyphicon glyphicon-repeat spinning loading">
                </span>
            </td>
        </tr>
    </tbody>
</table>
</template>

<script>
export default {
    props: ['initialItems'],
    data() { return { items: this.initialItems, saving: [] }; },
    computed: {
        unpackedItems() {
            return this.items.filter(item => {
                return item.checked_at_packing !== 1;
            });
        },
    },
    methods: {
        saveFragility(item) {
            this.saving[item.id] = true;
            axios.put('/packing/item/' + item.id + '/fragility/' + item.fragility)
                .then(response => {
                    this.items.forEach((item, index) => {
                        if (item.id === response.data.id) {
                            Vue.set(this.items, index, response.data);
                        }
                    });
                    this.$emit('item-update', item);
                    this.saving[item.id] = false;
                    if (this.unpackedItems.length === 0) {
                        this.$emit('all-fragilities-set');
                    }
                });
        },
    }
}
</script>
