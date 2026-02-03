PLUGIN_SLUG := product-handel
BUILD_DIR := build

.PHONY: build clean

build:
	@rm -f $(BUILD_DIR)/$(PLUGIN_SLUG).zip
	@mkdir -p $(BUILD_DIR)
	@ln -sfn . $(PLUGIN_SLUG)
	@zip -r $(BUILD_DIR)/$(PLUGIN_SLUG).zip \
		$(PLUGIN_SLUG)/product-handel.php \
		$(PLUGIN_SLUG)/includes/ \
		$(PLUGIN_SLUG)/assets/ \
		$(PLUGIN_SLUG)/admin/ \
		-x "$(PLUGIN_SLUG)/build/*" "*/.*"
	@rm $(PLUGIN_SLUG)
	@echo "Built: $(BUILD_DIR)/$(PLUGIN_SLUG).zip"

clean:
	rm -rf $(BUILD_DIR)
