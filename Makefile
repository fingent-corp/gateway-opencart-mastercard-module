all:
	@echo "Creating package: Mastercard.ocmod.zip"
	@git archive HEAD:src --format=zip -o mastercard.ocmod.zip

