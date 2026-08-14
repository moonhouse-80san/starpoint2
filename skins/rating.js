// 문서에 별점 평가하기
function doRate(document_srl, rate_srl){
	if (!confirm("정말 평가 하시겠습니까?")) return;
	exec_json("starpoint.procStarpointDoRateDocument", { doc_srl: document_srl, star_srl: rate_srl }, function () {
		alert("평가 완료 하였습니다.");
		location.reload();
	});
}

// 별점 평가 취소하기
function cancelRate(document_srl) {
	if (!confirm("평가를 취소하시겠습니까?")) return;
	exec_json("starpoint.procStarpointDeleteRating", { document_srl: document_srl }, function (ret) {
		if (ret.error == 0) {
			alert("평가가 취소되었습니다.");
			location.reload();
		} else {
			alert("평가 취소에 실패했습니다: " + ret.message);
		}
	});
}

// 별점 위젯 초기화 (페이지 로드 시 실행)
jQuery(function($){
	let selectedRating = 0;
	
	// 호버 시 점수 표시를 위한 요소 추가
	if($('.star-select').length > 0 && $('.star-select .hover-score').length === 0) {
		$('.star-select').append('<span class="hover-score"></span>');
	}
	
	// .star-select 방식
	$('.star-select').hover(
		function() {
			const value = $(this).data('value');
			
			// 마우스 오버 시 현재 별과 이전 별들 강조
			$('.star-select').each(function() {
				if($(this).data('value') <= value) {
					$(this).addClass('hover');
					$(this).find('.hover-score').text(value);
				}
			});
		},
		function() {
			// 마우스 아웃 시 선택한 것 외에 모두 초기화
			$('.star-select').removeClass('hover');
			$('.star-select .hover-score').text('');
			
			// 선택된 별 유지
			if(selectedRating > 0) {
				$('.star-select').each(function() {
					if($(this).data('value') <= selectedRating) {
						$(this).addClass('selected');
					}
				});
			}
		}
	);
	
	// 별 클릭 시
	$('.star-select').click(function() {
		selectedRating = $(this).data('value');
		
		// 모든 별 초기화 후 선택한 별까지 채우기
		$('.star-select').removeClass('selected');
		$('.star-select').each(function() {
			if($(this).data('value') <= selectedRating) {
				$(this).addClass('selected');
			}
		});
		
		// 선택된 점수 표시
		$('#selected-value').text(selectedRating);
	});
	
	// 평가하기 버튼 클릭
	$('#submit-rating').click(function() {
		const documentSrl = $(this).data('document-srl');
		
		if(selectedRating === 0) {
			alert('평점을 선택해주세요.');
			return;
		}
		
		doRate(documentSrl, selectedRating);
	});
	
	// 평가 취소 버튼 클릭
	$('#cancel-rating').click(function() {
		const documentSrl = $(this).data('document-srl');
		cancelRate(documentSrl);
	});
	
	// .rating-form 방식 (기존 코드 유지)
	$('.rating-form .stars label').hover(
		function() {
			$(this).find('.star').addClass('filled');
			$(this).prevAll('label').find('.star').addClass('filled');
		},
		function() {
			if(!$(this).find('input').prop('checked')) {
				$(this).find('.star').removeClass('filled');
			}
			$(this).prevAll('label').each(function(){
				if(!$(this).find('input').prop('checked')) {
					$(this).find('.star').removeClass('filled');
				}
			});
		}
	);
	
	$('.rating-form .stars label').click(function(){
		$('.rating-form .stars label .star').removeClass('filled');
		$(this).find('.star').addClass('filled');
		$(this).prevAll('label').find('.star').addClass('filled');
		var rating = $(this).find('input').val();
		console.log('선택한 평점: ' + rating);
	});
	
	$('.rating-form form').submit(function(e){
		e.preventDefault();
		var document_srl = $(this).find('input[name="document_srl"]').val();
		var rating = $(this).find('input[name="rating"]:checked').val();
		
		if(!rating) {
			alert('평점을 선택해주세요.');
			return false;
		}
		
		doRate(document_srl, rating);
		return false;
	});
});