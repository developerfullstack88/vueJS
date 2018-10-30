<template>
<div>
    <input type="hidden" name="box_price" :value="boxTotal">
    <input type="hidden" name="packing_price" :value="wrappingsTotal">
    <table class="table table-condensed">
        <thead>
            <tr>
                <td>Boxes</td>
                <td>Material</td>
                <td>Packing</td>
                <td>Shipping</td>
                <td>
                    <span class="visible-lg">Insurance</span>
                    <span class="hidden-lg">Ins.</span>
                </td>
                <td>Adjust</td>
                <td>Total</td>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>${{ boxTotal.toFixed(2) }}</td>
                <td>${{ wrappingsTotal.toFixed(2) }}</td>
                <td>${{ packingTimeTotal.toFixed(2) }}</td>
                <td>${{ shippingPrice.toFixed(2) }}</td>
                <td>${{ insurance.toFixed(2) }}</td>
                <td>
                    <div id="shipping-adjustment-box">
                        <input v-if="adjustmentEditable !== false"
                            type="number"
                            name="adjustment"
                            v-model="adjustment"
                            step="1"
                            placeholder="00.00"
                            class="form-control"
                        >
                        <div v-if="adjustmentEditable === false">{{ adjustment }}</div>
                    </div>
                </td>
                <td>${{ total.toFixed(2) }}</td>
            </tr>
        </tbody>
    </table>
</div>
</template>

<script>
export default {
    props: {
        boxTotal: { type: Number },
        wrappingsTotal: { type: Number },
        packingTimeTotal: { type: Number },
        shippingPrice: { type: Number },
        insurance: { type: Number },
        initialAdjustment: { type: Number },
        adjustmentEditable: { type: Boolean },
    },
    data: function() {
        return { adjustment: this.initialAdjustment || 0 };
    },
    computed: {
        /**
         * Total all packing material costs.
         */
        total: function() {
            return (parseFloat(this.boxTotal) || 0)
                + (parseFloat(this.wrappingsTotal) || 0)
                + (parseFloat(this.packingTimeTotal) || 0)
                + (parseFloat(this.shippingPrice) || 0)
                + (parseFloat(this.insurance) || 0)
                + (parseFloat(this.adjustment) || 0);
        },
    },
    watch: {
        adjustment(amount) {
            this.$emit('adjustment-update', amount);
        },
    },
}
</script>
