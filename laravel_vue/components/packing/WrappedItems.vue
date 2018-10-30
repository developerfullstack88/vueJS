<template>
<div>
<h4>Wrapped Items:</h4>
<div class="wrapped-items clearfix"
    ondragover="return false"
    ondragenter="return false"
    @drop.prevent="$emit('drop', $event)"
>
    <div v-for="(item, index) in items"
        :class="'item packing-icon ' + itemClasses[index]"
        :data-item-id="item.id"
        :id="'wrapped-item-'+item.id"
        :draggable="item.checked_at_packing ? 'true' : 'false'"
        @dragstart="$emit('drag-item', item, 'wrapped', $event)"
        @click="select(item)"
        data-toggle="popover"
    >
        <div class="img-text">
            <span class="bg-success text-success">{{ item.lot }}</span>
            {{ item.description }}
        </div>
        <b-popover :target="'wrapped-item-'+item.id" :show="item.showPopover" @show="hideAllPopovers">
            <select v-if=" ! item.checked_at_packing"
                class="form-control"
                @change="saveFragility(item, $event.target.value)"
            >
                <option selected disabled>Select Fragilitiy</option>
                <option>Standard</option>
                <option>Semi-Fragile</option>
                <option>Fragile</option>
                <option>Very Fragile</option>
                <option>Delicate</option>
            </select>

            <ul v-if="item.checked_at_packing" class="list-unstyled text-center">
                <li>
                    {{ item.lot }}
                    {{ item.description }}
                    ${{ item.bid_amount }}
                </li>
                <li v-for="box in item.box_inventories"
                    class="wrapping-material"
                    :data-pivot-id="box.pivot.id"
                >
                    {{ box.label }}
                    <span :data-item-id="item.id"
                        :data-box-id="box.id"
                        class="glyphicon glyphicon-trash text-danger"
                        @click="removeWrappingMaterial(box.id, item)"
                    ></span>
                </li>
            </ul>

            <span v-if="item.loading" class="glyphicon glyphicon-repeat spinning loading"></span>
        </b-popover>
    </div>
    <span v-if="loading" class="glyphicon glyphicon-repeat spinning loading"></span>
</div>
</div>
</template>

<script>
export default {
    props: ['items', 'loading'],
    computed: {
        itemClasses() {
            return this.items.map(function(item) {
                return (item.checked_at_packing ? 'checked ' : '') +
                    (item.selected ? 'selected ' : '');
            });
        },
    },

    methods: {
        select(item) {
            this.items.forEach(i => Vue.set(i, 'selected', false));
            Vue.set(item, 'selected', true);
        },

        removeWrappingMaterial: function(materialId, item) {
            // show loading icon
            Vue.set(item, 'loading', true);

            // send delete request
            var component = this;
            axios.delete('/packing/item/' + item.id + '/box/' + materialId)
                .then(function(response) {
                    var newItem = response.data;
                    newItem.showPopover = true;
                    component.$emit('update-item', newItem);
                });
        },
        saveFragility(item, fragility) {
            Vue.set(item, 'loading', true);
            axios.put('/packing/item/' + item.id + '/fragility/' + fragility)
                .then(response => {
                    this.$emit('update-item', response.data);
                });
        },
        hideAllPopovers() {
            this.$root.$emit('bv::hide::popover');
        },
   },
}
</script>
