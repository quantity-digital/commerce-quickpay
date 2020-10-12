
(function($){

	if (typeof Craft.Plans === 'undefined') {
		Craft.Plans = {};
	}

	Craft.Plans.PlanIndex = Craft.BaseElementIndex.extend(
	{
		editablePlanTypes: null,
		$newPlanBtnPlanType: null,
		$newPlanBtn: null,

		init: function(elementType, $container, settings) {

			this.on('selectSource', $.proxy(this, 'updateButton'));
			this.on('selectSite', $.proxy(this, 'updateButton'));
			this.base(elementType, $container, settings);
		},

		afterInit: function() {
			// Find which of the visible planTypes the user has permission to create new plans in
			this.editablePlanTypes = [];

			for (var i = 0; i < Craft.Plans.editablePlanTypes.length; i++) {
				var planType = Craft.Plans.editablePlanTypes[i];

				if (this.getSourceByKey('planType:' + planType.id)) {
					this.editablePlanTypes.push(planType);
				}
			}

			this.base();
		},

		getDefaultSourceKey: function() {
			// Did they request a specific planType in the URL?
			if (this.settings.context === 'index' && typeof defaultPlanTypeHandle !== 'undefined') {
				for (var i = 0; i < this.$sources.length; i++) {
					var $source = $(this.$sources[i]);

					if ($source.data('handle') === defaultPlanTypeHandle) {
						return $source.data('key');
					}
				}
			}

			return this.base();
		},

		updateButton: function() {
			if (!this.$source) {
				return;
			}

			// Get the handle of the selected source
			var selectedSourceHandle = this.$source.data('handle');

			var i, href, label;

			// Update the New Plan button
			// ---------------------------------------------------------------------

			if (this.editablePlanTypes.length) {
				// Remove the old button, if there is one
				if (this.$newPlanBtnPlanType) {
					this.$newPlanBtnPlanType.remove();
				}

				// Determine if they are viewing a planType that they have permission to create plans in
				var selectedplanType;

				if (selectedSourceHandle) {
					for (i = 0; i < this.editablePlanTypes.length; i++) {
						if (this.editablePlanTypes[i].handle === selectedSourceHandle) {
							selectedplanType = this.editablePlanTypes[i];
							break;
						}
					}
				}

				this.$newPlanBtnPlanType = $('<div class="btngroup submit"/>');
				var $menuBtn;

				// If they are, show a primary "New Plan" button, and a dropdown of the other planTypes (if any).
				// Otherwise only show a menu button
				if (selectedplanType) {
					href = this._getplanTypeTriggerHref(selectedplanType);
					label = (this.settings.context === 'index' ? Craft.t('app', 'New Plan') : Craft.t('app', 'New {planType} Plan', {planType: selectedplanType.name}));
					this.$newPlanBtn = $('<a class="btn submit add icon" ' + href + '>' + Craft.escapeHtml(label) + '</a>').appendTo(this.$newPlanBtnPlanType);

					if (this.settings.context !== 'index') {
						this.addListener(this.$newPlanBtn, 'click', function(ev) {
							this._openCreatePlanModal(ev.currentTarget.getAttribute('data-id'));
						});
					}

					if (this.editablePlanTypes.length > 1) {
						$menuBtn = $('<div class="btn submit menubtn"></div>').appendTo(this.$newPlanBtnPlanType);
					}
				}
				else {
					this.$newPlanBtn = $menuBtn = $('<div class="btn submit add icon menubtn">' + Craft.t('app', 'New Plan') + '</div>').appendTo(this.$newPlanBtnPlanType);
				}

				if ($menuBtn) {
					var menuHtml = '<div class="menu"><ul>';

					for (i = 0; i < this.editablePlanTypes.length; i++) {
						var planType = this.editablePlanTypes[i];

						if (this.settings.context === 'index' || planType !== selectedplanType) {
							href = this._getplanTypeTriggerHref(planType);
							label = (this.settings.context === 'index' ? planType.name : Craft.t('app', 'New {planType} Plan', {planType: planType.name}));
							menuHtml += '<li><a ' + href + '">' + Craft.escapeHtml(label) + '</a></li>';
						}
					}

					menuHtml += '</ul></div>';

					$(menuHtml).appendTo(this.$newPlanBtnPlanType);
					var menuBtn = new Garnish.MenuBtn($menuBtn);

					if (this.settings.context !== 'index') {
						menuBtn.on('optionSelect', $.proxy(function(ev) {
							this._openCreatePlanModal(ev.option.getAttribute('data-id'));
						}, this));
					}
				}

				this.addButton(this.$newPlanBtnPlanType);
			}

			// Update the URL if we're on the Plans index
			// ---------------------------------------------------------------------

			if (this.settings.context === 'index' && typeof history !== 'undefined') {
				var uri = 'commerce-quickpay/plans';

				if (selectedSourceHandle) {
					uri += '/' + selectedSourceHandle;
				}

				history.replaceState({}, '', Craft.getUrl(uri));
			}
		},

		_getplanTypeTriggerHref: function(planType) {
			if (this.settings.context === 'index') {
				var uri = 'commerce-quickpay/plans/' + planType.handle + '/new';
				if (this.siteId && this.siteId != Craft.primarySiteId) {
					for (var i = 0; i < Craft.sites.length; i++) {
						if (Craft.sites[i].id == this.siteId) {
							uri += '/'+Craft.sites[i].handle;
						}
					}
				}
				return 'href="' + Craft.getUrl(uri) + '"';
			}
			else {
				return 'data-id="' + planType.id + '"';
			}
		},

		_openCreatePlanModal: function(planTypeId) {
			if (this.$newPlanBtn.hasClass('loading')) {
				return;
			}

			// Find the planType
			var planType;

			for (var i = 0; i < this.editablePlanTypes.length; i++) {
				if (this.editablePlanTypes[i].id == planTypeId) {
					planType = this.editablePlanTypes[i];
					break;
				}
			}

			if (!planType) {
				return;
			}

			this.$newPlanBtn.addClass('inactive');
			var newPlanBtnText = this.$newPlanBtn.text();
			this.$newPlanBtn.text(Craft.t('app', 'New {planType} Plan', {planType: planType.name}));

			Craft.createElementEditor(this.elementType, {
				hudTrigger: this.$newPlanBtnPlanType,
				elementType: 'kuriousagency\\commerce\\plans\\elements\\Plan',
				siteId: this.siteId,
				attributes: {
					planTypeId: planTypeId
				},
				onBeginLoading: $.proxy(function() {
					this.$newPlanBtn.addClass('loading');
				}, this),
				onEndLoading: $.proxy(function() {
					this.$newPlanBtn.removeClass('loading');
				}, this),
				onHideHud: $.proxy(function() {
					this.$newPlanBtn.removeClass('inactive').text(newPlanBtnText);
				}, this),
				onSaveElement: $.proxy(function(response) {
					// Make sure the right planType is selected
					var planTypeSourceKey = 'planType:' + planTypeId;

					if (this.sourceKey !== planTypeSourceKey) {
						this.selectSourceByKey(planTypeSourceKey);
					}

					this.selectElementAfterUpdate(response.id);
					this.updateElements();
				}, this)
			});
		}
	});

	// Register it!
	Craft.registerElementIndexClass('QD\\commerce\\quickpay\\elements\\Plan', Craft.Plans.PlanIndex);

	})(jQuery);
