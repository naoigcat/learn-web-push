.PHONY: keygen
keygen:
	cd keys ; \
	openssl ecparam -genkey -name prime256v1 -noout -out private.pem ; \
	openssl ec -in private.pem -pubout -out public.pem ;
