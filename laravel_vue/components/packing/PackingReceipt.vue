<template>
<div class="col-xs-12">
    <table class="table table-striped text-left">
        <thead><tr><th>Qty</th><th>Description</th><th>Price</th></tr></thead>
        <tbody>
            <tr v-for="box in boxes">
                <td>{{ box.quantity }}</td>
                <td>{{ box.label }}</td>
                <td>${{ box.cost.toFixed(2) }}</td>
            </tr>
            <tr v-for="wrapping in wrappings">
                <td>{{ wrapping.quantity }}</td>
                <td>{{ wrapping.label }}</td>
                <td>${{ wrapping.cost.toFixed(2) }}</td>
            </tr>
        </tbody>
    </table>
</div>
</template>

<script>
export default {
    props: ['items', 'packages'],
    computed: {
        /**
         * Combine packages into list of box quantities used.
         */
        boxes: function() {
            var boxes = [];
            this.packages.forEach(function(thePackage) {
                if (typeof thePackage.box.retail_price === 'undefined') {
                    return;
                }
                if (typeof boxes[thePackage.box.id] === 'undefined') {
                    boxes[thePackage.box.id] = {
                        quantity: 1,
                        label: thePackage.box.label,
                        cost: thePackage.box.retail_price,
                    };
                } else {
                    boxes[thePackage.box.id]['quantity']++;
                    boxes[thePackage.box.id]['cost'] += thePackage.box.retail_price;
                }
            });
            // return sequentially indexed array
            return Object.keys(boxes).map(function(key) { return boxes[key]; });
        },
        /**
         * Combine wrapping material.
         */
        wrappings: function() {
            var wrappings = [];
            this.items.forEach(function(item) {
                item.box_inventories.forEach(function(box) {
                    if (typeof wrappings[box.id] === 'undefined') {
                        wrappings[box.id] = {
                            quantity: 1,
                            label: box.label,
                            cost: box.retail_price,
                        };
                    } else {
                        wrappings[box.id]['quantity']++;
                        wrappings[box.id]['cost'] += box.retail_price;
                    }
                });
            });
            // return sequentially indexed array
            return Object.keys(wrappings).map(function(key) { return wrappings[key]; });
        },
    },
}
</script>
