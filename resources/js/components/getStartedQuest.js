export function getStartedQuest() {
	return {
		visible: true,
		dismissing: false,
		async dismiss(event) {
			if (this.dismissing) {
				return;
			}

			this.dismissing = true;
			const form = event.currentTarget;
			const section = this.$el;

			try {
				await fetch(form.action, {
					method: 'POST',
					headers: {
						'X-Requested-With': 'XMLHttpRequest',
						'X-CSRF-TOKEN': form.querySelector('input[name="_token"]').value,
					},
					body: new FormData(form),
				});
			} catch (error) {
				this.dismissing = false;
				return;
			}

			this.visible = false;
		},
	};
}