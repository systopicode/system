rutancms.lib.message = function(id){
	var message = this;
	if(!message.prototype.messages){
		message.prototype.messages = {}
	}
	var messages = message.prototype.messages
	messages[id] = messages;
	
}

