/* global sys,c,p,t,l */
(function () {
	var setup = {// setup closure
		info: 'users sigleton - user manager',
		route: 'singletons.users',
		shorthand: 'users'
	};
	sys.lib.add(setup, function () {
		var users = this; // instance closure
		var id, ids = {};
		// ***************** color class ***************** //
		var user = function (data) {
			var user = this;
			ids[id] = user;
			for (var key in data) {
				user[key] = data[key];
			}
			user.getRoles = () => {
				if (user.isActive && users.active_fake_roles.length) {
					return users.active_fake_roles;
				}
				if (user.isSuperuser) {
					return users.superuser_roles;
				}
				return user.role_names;
			}
			user.hasRole = role => {
				return user.getRoles().includes(role);
			}
		};
		users.getUser = id => {
			if (ids['#' + id]) {
				return ids['#' + id];
			}
			return new user({name: ' - - - '})
		};

		// ***************** create users ***************** //
		for (var key in sys.singletons.users.__data) {
			users[key] = sys.singletons.users.__data[key];
		}

		for (var id in users.ids) {
			new user(users.ids[id]);
			if (ids[id].id === users.active) {
				users.active = ids[id];
				users.active.isActive = true;
			}
		}
		if (!users.active) {
			users.active = new user({role_names: []});
		}
	});
})();
		