COMPANY_NAME 		= Optimizely
PRODUCT 			= Campaign
PLUGIN_VERSION_TAG 	= v_1_0_11
OUTPUT_ZIP 			= ${COMPANY_NAME}${PRODUCT}SW6_${PLUGIN_VERSION_TAG}.zip
FILES 				= $(shell find . -type f ! -name "Makefile" ! -name "README.md" ! -path "./.git/*" ! -path "./.github/*" | grep -v "/\.DS_Store" | grep -v "/\.gitignore")

.PHONY: all clean

all: $(OUTPUT_ZIP)

$(OUTPUT_ZIP): $(FILES)
	zip -r $(OUTPUT_ZIP) $^

clean:
	rm -f $(OUTPUT_ZIP)