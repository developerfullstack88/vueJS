<template>
<div id="label-modal" class="modal fade" tabindex="-1" role="dialog">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
        <h4 class="modal-title">Confirm Labels Printed</h4>
      </div>
      <div class="modal-body">
        <table class="table table-striped bs-reset">
            <thead><tr><th></th><th>Customer</th><th>Location</th></tr></thead>
            <tbody>
                <tr v-for="thePackage in selectedPackages">
                    <td>
                        <input type="checkbox"
                            v-model="selectedPackageIds"
                            :value="thePackage.id"
                            :disabled="thePackage.info['tracking_code'] ? false : true"
                        >
                    </td>
                    <td>
                        {{ thePackage.items[0].customer.first_name }}
                        {{ thePackage.items[0].customer.last_name }}
                    </td>
                    <td>{{ (thePackage.items[0].locations || {}).location }}</td>
                </tr>
            </tbody>
        </table>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
        <button type="button" class="btn btn-primary" @click="moveToShipped">Confirm Printed</button>
      </div>
    </div><!-- /.modal-content -->
  </div><!-- /.modal-dialog -->
</div><!-- /.modal -->
</template>

<script>
export default {
    props: ['shipmentOrders', 'initialSelectedOrderIds'],
    data() {
        return {
            selectedOrderIds: [],
            selectedPackageIds: []
        };
    },
    computed: {
        selectedOrders() {
            return this.initialSelectedOrderIds.map(id => this.shipmentOrders[id]);
        },
        selectedPackages() {
            var packages = [];
            // combine packages, filtering out those without a tracking code
            this.selectedOrders.forEach(order => {
                packages = packages.concat(order.packages.filter(p => p.info && p.info['tracking_code']));
            });
            // sort packages by location
            return packages.sort((a, b) => {
                var locationA = (a.items[0].locations || {}).location || 0;
                var locationB = (b.items[0].locations || {}).location || 0;
                locationA = isNaN(locationA) ? locationA : parseFloat(locationA);
                locationB = isNaN(locationB) ? locationB : parseFloat(locationB);
                return locationA < locationB ? -1 : 1;
            });
        },
    },
    methods: {
        moveToShipped() {
            axios.put('shipping/shipped', { package_ids: this.selectedPackages.map(p => p.id) })
                .then(function() {
                    window.location.reload();
                });
        },
    },
}
</script>
