PLUGIN_NAME = pix-ai-payment-gateway
ZIP_NAME = $(PLUGIN_NAME).zip

.PHONY: all clean zip

# Default target
all: zip

# Create a ZIP file of the plugin
zip:
	@echo "Creating ZIP archive for $(PLUGIN_NAME)..."
	@rm -f $(ZIP_NAME)  # Remove existing ZIP if it exists
	@zip -r $(ZIP_NAME) $(PLUGIN_NAME) -x "*.git*" -x "*.DS_Store" -x "__MACOSX/*"
	@echo "ZIP file created: $(ZIP_NAME)"

# Clean up any existing ZIP files
clean:
	@echo "Cleaning up..."
	@rm -f $(ZIP_NAME)
	@echo "Clean completed!"