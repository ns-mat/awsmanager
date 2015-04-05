/*global ko Router */
(function () {
    // main view model
    var InstancesViewModel = function () {
	var self = this;

	// Data
	self.instances = ko.observable();

	// Behaviours
	self.getInstances = function() {
	    url = '/api/ec2-describe-instances.php';
	    $.get(url, { }, function(data) {
		    self.instances(data);
		});
	};

	// start instance
	self.startInstance = function(instance) {
	    url = '/api/ec2-start-instances.php';
	    $.get(url, { 'instance-id': instance.id }, function(data) {
		    console.log(data);
		    self.getInstances();
		});
	};

	// stop instance
	self.stopInstance = function(instance) {
	    if (! confirm("このインスタンスを停止してもよろしいですか?")) {
		return;
	    }

	    url = '/api/ec2-stop-instances.php';
	    $.get(url, { 'instance-id': instance.id }, function(data) {
		    console.log(data);
		    self.getInstances();
		});
	};

	self.status = function(state) {
	    if (state == 'running') {
		return 1;
	    }

	    if (state == 'stopped') {
		return 2;
	    }

	    return 0;
	}

	// Show instance list by default
	self.getInstances();
    };

    // bind a new instance of our view model to the page
    ko.applyBindings(new InstancesViewModel());

})();
