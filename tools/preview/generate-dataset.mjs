import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const previewDirectory = path.dirname( fileURLToPath( import.meta.url ) );
const contentPath = path.join( previewDirectory, 'preview-content.json' );
const mediaPath = path.join( previewDirectory, 'preview-media.json' );

const mediaManifest = JSON.parse( fs.readFileSync( mediaPath, 'utf8' ) );
const baseAssets = mediaManifest.assets.filter( ( asset ) => ! asset.id.endsWith( '-en' ) );
const photoAssetIds = baseAssets
	.map( ( asset ) => asset.id )
	.filter( ( assetId ) => assetId.startsWith( 'photo-' ) );

const englishAltText = [
	'A decorated courtyard prepared for a Buddhist celebration in Sa Dec',
	'The entrance to Thien Lam Pagoda in Vung Tau',
	'The tree-lined facade of Thanh Pagoda in Lang Son',
	'The courtyard of Dai Giac Pagoda beneath a mature tree',
	'An ancient bell in the grounds of Thien Mu Pagoda',
	'Visitors walking through a Vietnamese temple complex',
	'A quiet pagoda roof framed by trees and open sky',
	'A Buddhist statue overlooking a temple courtyard',
	'Decorative details preserved on a historic pagoda',
	'A rural temple landscape in the late afternoon',
];
const englishAssets = photoAssetIds.slice( 0, 10 ).map( ( assetId, index ) => {
	const asset = baseAssets.find( ( candidate ) => candidate.id === assetId );
	return {
		...asset,
		id: `${ asset.id }-en`,
		source_title: `Vietnam Buddhist heritage photograph ${ index + 1 }`,
		description: 'An attributed development fixture used by the English-language Vietnam Buddhism editorial surface.',
		alt: englishAltText[ index ],
	};
} );
const englishPhotoAssetIds = englishAssets.map( ( asset ) => asset.id );
mediaManifest.assets = [ ...baseAssets, ...englishAssets ];

const videoCatalog = [
	{ id: 'A20Lx3sv8hs', poster: 'video-poster-0008' },
	{ id: 'X3iS2L0Y_xw', poster: 'video-poster-0012' },
	{ id: '2ZSW6vPbEjo', poster: 'video-poster-0019' },
	{ id: 'fTcmiAD_GwQ', poster: 'video-poster-0039' },
];

const vietnameseAngles = [
	'những ghi nhận từ một ngày nhiều kết nối',
	'câu chuyện phía sau những việc làm bền bỉ',
	'khi trải nghiệm nhỏ mở ra thay đổi lớn',
	'một cách tiếp cận gần gũi và thực tế',
	'điều còn lại sau những cuộc gặp gỡ',
	'góc nhìn từ người trực tiếp tham gia',
	'những chi tiết tạo nên giá trị lâu dài',
	'từ ý tưởng ban đầu đến thực hành mỗi ngày',
	'một hành trình được kể bằng hình ảnh',
	'bài học về sự lắng nghe và sẻ chia',
	'những chuyển động mới trong đời sống hôm nay',
	'cách cộng đồng cùng gìn giữ một giá trị đẹp',
	'thêm một lựa chọn cho nhịp sống cân bằng',
	'khi truyền thống gặp nhu cầu của người trẻ',
	'một lát cắt bình dị nhưng nhiều gợi mở',
	'những kinh nghiệm có thể áp dụng ngay',
	'để tinh thần phụng sự đi vào việc cụ thể',
	'câu chuyện về trách nhiệm và lòng tin',
	'nhìn lại tiến trình bằng dữ liệu và trải nghiệm',
	'mở rộng không gian đối thoại trong cộng đồng',
];

const englishAngles = [
	'notes from a day of meaningful connection',
	'the people and practices behind lasting change',
	'how a small experience can reshape a community',
	'a practical approach rooted in everyday life',
	'what remains after the gathering ends',
	'perspectives from people taking part',
	'the details that sustain a shared tradition',
	'from an initial idea to a daily practice',
	'a visual journey through living heritage',
	'lessons in listening, care and participation',
	'new movements in contemporary Buddhist life',
	'how communities preserve a valued practice',
	'an invitation to a more balanced rhythm',
	'where tradition meets a younger generation',
	'an ordinary scene with wider implications',
	'experiences that can inform future work',
	'turning service into concrete action',
	'a story of responsibility and trust',
	'reviewing progress through evidence and experience',
	'expanding space for dialogue and belonging',
];

const categoryDetails = {
	'tin-phat-su': {
		heading: 'Thông tin sự kiện và trách nhiệm xác minh',
		paragraph: 'Với một bản tin Phật sự, mốc thời gian, địa điểm, đơn vị tổ chức và quy mô tham dự cần được đặt trong cùng một mạch thông tin. Fixture vì vậy mô phỏng cả phần chuẩn bị, diễn biến và công việc nối tiếp, giúp biên tập viên kiểm tra sapo, trật tự dữ kiện và cách bài liên kết đến hoạt động cộng đồng.',
	},
	'song-an-lanh': {
		heading: 'Một thực hành có thể lặp lại',
		paragraph: 'Nội dung Sống an lành tránh những lời khuyên tuyệt đối. Mỗi bài chọn một thực hành nhỏ, mô tả hoàn cảnh áp dụng, dấu hiệu cần điều chỉnh và cách tự quan sát sau vài ngày. Nhờ đó người đọc có thể thử nghiệm trong đời sống thật mà không xem bài viết như tư vấn y khoa hoặc một công thức phù hợp với tất cả mọi người.',
	},
	'am-thuc-chay': {
		heading: 'Từ nguyên liệu đến một bữa ăn cân bằng',
		paragraph: 'Bài Ẩm thực chay chú ý mùa vụ, nhóm chất, khẩu phần và cách tận dụng nguyên liệu còn lại. Các bước chế biến được kể cùng bối cảnh gia đình hoặc bếp cộng đồng, để ảnh món ăn không đứng riêng khỏi câu chuyện dinh dưỡng, chi phí và giảm lãng phí trong căn bếp hằng ngày.',
	},
	'loi-song-xanh': {
		heading: 'Đo tác động bằng thay đổi cụ thể',
		paragraph: 'Một lựa chọn xanh chỉ có ý nghĩa khi có thể duy trì. Bài viết ghi nhận lượng vật dụng được giảm, nguồn lực cần thiết, người chịu trách nhiệm và trở ngại sau giai đoạn thử nghiệm. Góc nhìn này giúp chuyên mục không dừng ở khẩu hiệu, đồng thời tạo dữ liệu phong phú cho danh sách, tìm kiếm và bài liên quan.',
	},
	'phat-tich': {
		heading: 'Đọc di sản từ nhiều lớp tư liệu',
		paragraph: 'Nội dung Phật tích phân biệt điều quan sát tại hiện trường, ký ức truyền miệng và dữ kiện cần đối chiếu từ tư liệu. Chi tiết về vật liệu, niên đại, lần trùng tu và không gian cảnh quan được sắp xếp để người đọc hiểu giá trị di sản, thay vì chỉ nhìn công trình như một phông nền đẹp cho hình ảnh.',
	},
	'tot-doi-dep-dao': {
		heading: 'Phụng sự với mục tiêu và điểm dừng rõ ràng',
		paragraph: 'Bài Tốt đời – đẹp đạo theo dõi nhu cầu của người nhận, cách huy động nguồn lực và cơ chế phản hồi sau chương trình. Fixture chủ động mô tả giới hạn, chi phí duy trì và phương án bàn giao, qua đó giúp kiểm tra một bài xã hội có chiều sâu mà vẫn tôn trọng phẩm giá của cộng đồng được hỗ trợ.',
	},
	'emagazine': {
		heading: 'Một tuyến kể chuyện bằng văn bản và hình ảnh',
		paragraph: 'E-magazine được tổ chức như một hành trình thị giác: cảnh mở đầu tạo không khí, chân dung đưa câu chuyện đến gần con người, còn tư liệu và chi tiết không gian cung cấp chiều sâu. Ảnh rộng, ảnh toàn màn hình, chú thích, gallery và pull quote đều là thành phần nội dung có chủ ý chứ không chỉ để trang trí.',
	},
};

const categories = [
	{
		slug: 'tin-phat-su',
		label: 'Tin Phật sự',
		parent: '',
		focus: 'hoạt động Phật sự, tự viện và cộng đồng Phật tử',
		subjects: [
			'Đại lễ Phật đản trong không gian di sản',
			'Mùa an cư và nhịp sinh hoạt tại tự viện',
			'Khóa tu mùa hè dành cho thanh thiếu niên',
			'Ngày hội văn hóa Phật giáo tại địa phương',
			'Chương trình giao lưu giữa các thế hệ Phật tử',
			'Lễ tưởng niệm và hoạt động tri ân cộng đồng',
			'Tọa đàm về truyền thông Phật giáo trong thời đại số',
			'Tuần lễ đọc sách và tìm hiểu giáo lý',
			'Hành trình kết nối các đạo tràng trẻ',
			'Không gian sinh hoạt mới của một ngôi chùa quê',
		],
	},
	{
		slug: 'song-an-lanh',
		label: 'Sống an lành',
		parent: '',
		focus: 'thực hành chánh niệm và xây dựng nhịp sống cân bằng',
		subjects: [
			'Một buổi sáng dành cho sự tĩnh lặng',
			'Thực tập lắng nghe trong gia đình',
			'Bảy ngày sắp xếp lại nhịp sống',
			'Khoảng nghỉ ngắn giữa ngày làm việc',
			'Đi bộ chánh niệm trong thành phố',
			'Giữ bình tâm trước một cuộc trò chuyện khó',
			'Tạo góc đọc sách và thiền tập tại nhà',
			'Học cách nhận biết cảm xúc đang đến',
			'Một cuối tuần không lịch trình dày đặc',
			'Nuôi dưỡng thói quen biết ơn mỗi tối',
		],
	},
	{
		slug: 'am-thuc-chay',
		label: 'Ẩm thực chay',
		parent: 'song-an-lanh',
		focus: 'ẩm thực chay theo mùa, đủ dinh dưỡng và giảm lãng phí',
		subjects: [
			'Mâm cơm chay theo mùa cho gia đình',
			'Bữa sáng thực vật nhanh và đủ chất',
			'Rau củ địa phương trong căn bếp nhỏ',
			'Nấu ăn chay cho người mới bắt đầu',
			'Một tuần chuẩn bị thực phẩm không lãng phí',
			'Món chay truyền thống trong ngày lễ',
			'Đạm thực vật và cách phối hợp nguyên liệu',
			'Bếp chay cộng đồng phục vụ người khó khăn',
			'Gia vị tự nhiên cho món ăn thanh nhẹ',
			'Bữa cơm chung kết nối ba thế hệ',
		],
	},
	{
		slug: 'loi-song-xanh',
		label: 'Lối sống xanh',
		parent: 'song-an-lanh',
		focus: 'lựa chọn tiêu dùng có trách nhiệm và chăm sóc môi trường sống',
		subjects: [
			'Ngày không nhựa dùng một lần tại tự viện',
			'Khu vườn nhỏ nuôi dưỡng thói quen xanh',
			'Phân loại rác từ những bước đầu tiên',
			'Sửa chữa đồ cũ thay vì mua mới',
			'Tiết kiệm nước trong sinh hoạt hằng ngày',
			'Tủ đồ tối giản và lựa chọn có ý thức',
			'Đi chợ với túi dùng nhiều lần',
			'Không gian xanh cho khu dân cư đông đúc',
			'Chuyến đi gần nhà giảm dấu chân carbon',
			'Nhóm trẻ cùng làm sạch đường làng',
		],
	},
	{
		slug: 'phat-tich',
		label: 'Phật tích',
		parent: '',
		focus: 'di sản kiến trúc, lịch sử tự viện và ký ức của cộng đồng',
		subjects: [
			'Mái ngói cổ và dấu vết thời gian',
			'Chuông chùa trong ký ức một vùng quê',
			'Hành trình đọc lại văn bia cổ',
			'Ngôi chùa ven sông qua nhiều lần trùng tu',
			'Vườn tháp và câu chuyện truyền thừa',
			'Bảo tồn tượng gỗ trong điều kiện khí hậu ẩm',
			'Con đường hành hương qua miền di sản',
			'Nghệ thuật chạm khắc trên cửa chùa',
			'Không gian sân chùa trong đời sống làng',
			'Tư liệu ảnh về một cổ tự miền Trung',
		],
	},
	{
		slug: 'tot-doi-dep-dao',
		label: 'Tốt đời – đẹp đạo',
		parent: '',
		focus: 'hoạt động thiện nguyện, giáo dục và phụng sự xã hội',
		subjects: [
			'Bếp ăn sẻ chia tại bệnh viện',
			'Điểm đọc sách miễn phí cho trẻ em',
			'Chương trình nước sạch ở vùng cao',
			'Nhóm tình nguyện sửa nhà sau bão',
			'Ngày hội hiến máu của Phật tử trẻ',
			'Lớp kỹ năng số dành cho người cao tuổi',
			'Quỹ học bổng tiếp sức học sinh khó khăn',
			'Chuyến xe đưa người bệnh về quê',
			'Vườn rau cộng đồng hỗ trợ bữa ăn',
			'Người trẻ phục dựng sân chơi trong xóm',
		],
	},
	{
		slug: 'emagazine',
		label: 'E-magazine',
		parent: 'media',
		focus: 'câu chuyện dài giàu hình ảnh về văn hóa và đời sống Phật giáo',
		subjects: [
			'E-magazine: Một ngày trong ngôi chùa cổ',
			'E-magazine: Theo dấu người giữ nghề làm tượng',
			'E-magazine: Dòng sông và ký ức mùa lễ hội',
			'E-magazine: Những người trẻ trở về làng',
			'E-magazine: Hành trình của một cuốn kinh xưa',
			'E-magazine: Bếp chay giữa lòng thành phố',
			'E-magazine: Tiếng chuông qua bốn mùa',
			'E-magazine: Con đường lên ngôi chùa trên núi',
			'E-magazine: Phía sau một dự án thiện nguyện',
			'E-magazine: Khu vườn chữa lành của cộng đồng',
		],
	},
	{
		slug: 'video',
		label: 'Video',
		parent: 'media',
		focus: 'phóng sự, hướng dẫn thực hành và câu chuyện kể bằng hình ảnh',
		subjects: [
			'Video: Toàn cảnh ngày hội văn hóa Phật giáo',
			'Video: Mười lăm phút thực tập hơi thở',
			'Video: Khám phá một ngôi chùa ven biển',
			'Video: Nghệ thuật lắng nghe chân thành',
			'Video: Theo chân bếp ăn thiện nguyện',
			'Video: Câu chuyện bảo tồn mái chùa cổ',
			'Video: Một ngày sống xanh tại tự viện',
			'Video: Người trẻ học viết thư pháp',
			'Video: Hành trình trao sách vùng cao',
			'Video: Bữa cơm chay đủ chất cho gia đình',
		],
	},
	{
		slug: 'vietnam-buddhism',
		label: 'Vietnam Buddhism',
		parent: '',
		language: 'en',
		focus: 'Vietnamese Buddhist heritage, practice and contemporary community life',
		subjects: [
			'Buddhist Heritage Along the Perfume River',
			'How Young Volunteers Support Temple Communities',
			'A Practical Introduction to Walking Meditation',
			'Vegetarian Food Traditions in Vietnamese Families',
			'Preserving Historic Pagodas in a Changing Climate',
			'Community Libraries and Buddhist Learning',
			'The Role of Calligraphy in Festival Culture',
			'Listening Practices Across Generations',
			'Women Sustaining Local Buddhist Networks',
			'A Visual Journey Through Rural Temple Life',
		],
	},
];

function slugify( value ) {
	return value
		.normalize( 'NFD' )
		.replace( /[\u0300-\u036f]/g, '' )
		.replace( /đ/g, 'd' )
		.replace( /Đ/g, 'D' )
		.toLowerCase()
		.replace( /[^a-z0-9]+/g, '-' )
		.replace( /^-|-$/g, '' );
}

function buildVietnameseBody( title, definition, index, includeInline ) {
	const marker = includeInline ? '<!--pgds-preview-inline-image-->' : '';
	const detail = categoryDetails[ definition.slug ];
	const magazineExtension = definition.slug === 'emagazine' ? [
		'<h2>Chương hai: Những lớp ký ức trong cùng một không gian</h2>',
		'<p>Buổi trưa, ánh sáng thay đổi và những chi tiết từng nằm ngoài khung hình bắt đầu hiện rõ. Một vết mòn trên bậc đá cho thấy lối đi quen thuộc; lớp màu khác nhau trên cột gỗ gợi lại nhiều lần tu sửa; tiếng trò chuyện từ gian bếp kéo câu chuyện kiến trúc trở về với đời sống. Tuyến bài dành chỗ cho những quan sát chậm như vậy để mỗi hình ảnh bổ sung thông tin, thay vì lặp lại điều văn bản đã nói.</p>',
		'<p>Nhóm thực hiện lập một bảng ảnh gồm toàn cảnh, trung cảnh, chân dung và chi tiết. Mỗi ảnh có mục đích biên tập, chú thích nêu được địa điểm hoặc hành động, và thông tin nguồn được giữ trong Media Library. Cách tổ chức này cho phép người biên tập thử thay cover, kéo ảnh wide hoặc full, dựng một cặp ảnh đối chiếu và kiểm tra xem nhịp đọc có còn mạch lạc trên màn hình nhỏ hay không.</p>',
		'<h2>Chương ba: Điều được trao lại sau hành trình</h2>',
		'<p>Khi câu chuyện chuyển sang buổi chiều, trọng tâm không còn là một công trình hay một sự kiện riêng lẻ mà là cách con người tiếp nối giá trị đã nhận. Người lớn tuổi giữ ký ức, người trẻ học cách số hóa tư liệu, còn nhóm phục vụ lo những việc rất thực tế như lịch mở cửa, lối tiếp cận và hướng dẫn khách. Các vai trò gặp nhau trong một kế hoạch nhỏ, đủ rõ để có thể đánh giá sau mỗi mùa hoạt động.</p>',
		'<p>Phần kết đặt hình ảnh hiện tại cạnh câu hỏi về tương lai. Điều gì cần được bảo tồn nguyên trạng, điều gì có thể thích nghi và ai sẽ chịu trách nhiệm theo dõi? Tuyến bài không cố trả lời thay cộng đồng. Nó ghi lại những lựa chọn đang được cân nhắc và cho người đọc thấy rằng di sản sống luôn cần đối thoại, nguồn lực và sự chăm sóc qua nhiều thế hệ.</p>',
	] : [];
	return [
		`<p><strong>${ title }</strong> là bài viết mô phỏng dành cho môi trường phát triển PGDS. Nội dung được xây dựng như một hồ sơ biên tập hoàn chỉnh về ${ definition.focus }, với bối cảnh, nhân vật và số liệu đều mang tính minh họa. Mục tiêu là giúp người dùng kiểm tra bố cục bài dài, nhịp đọc, ảnh đại diện và các quan hệ nội dung trong CMS.</p>`,
		'<p>Câu chuyện bắt đầu từ những quan sát rất cụ thể: cách mọi người chuẩn bị không gian, phân chia công việc, tiếp nhận người mới và ghi lại điều cần cải thiện. Thay vì chỉ mô tả kết quả, bài viết chú ý đến tiến trình, những quyết định nhỏ và lý do khiến một hoạt động có thể duy trì lâu dài. Đây cũng là cách dữ liệu preview tạo cảm giác gần với một bài báo thật mà không gán phát ngôn cho tổ chức hay cá nhân có thật.</p>',
		'<h2>Bắt đầu từ nhu cầu của cộng đồng</h2>',
		`<p>Ở lát cắt thứ ${ index + 1 } của chuyên mục ${ definition.label }, nhóm biên tập giả định dành thời gian hỏi người tham gia về nhu cầu trước khi đưa ra giải pháp. Người cao tuổi quan tâm đến khả năng tiếp cận, người trẻ muốn được giao việc có trách nhiệm, còn gia đình cần thông tin rõ ràng về thời gian và cách tham gia. Khi các nhu cầu được đặt cạnh nhau, kế hoạch trở nên thực tế và dễ kiểm chứng hơn.</p>`,
		'<p>Một bảng công việc ngắn được sử dụng để theo dõi người phụ trách, thời hạn, nguồn lực và phản hồi sau hoạt động. Cách làm này không biến tinh thần phụng sự thành thủ tục; ngược lại, nó giúp lòng tốt đi đến đúng người và tránh phụ thuộc vào trí nhớ của một vài cá nhân. Những việc chưa hoàn thành cũng được ghi nhận thẳng thắn để lần tổ chức sau có điểm bắt đầu rõ ràng.</p>',
		`<h2>${ detail.heading }</h2>`,
		`<p>${ detail.paragraph }</p>`,
		marker,
		'<blockquote><p>Giá trị bền vững không nằm ở một khoảnh khắc nổi bật, mà ở khả năng cùng nhau chăm sóc những việc nhỏ sau khi chương trình đã kết thúc.</p></blockquote>',
		'<h2>Từ trải nghiệm đến thực hành hằng ngày</h2>',
		'<p>Phần tiếp theo nhìn vào cách kinh nghiệm được chuyển thành thói quen. Một cuộc họp ngắn sau chương trình, vài câu hỏi mở và bản tổng kết dễ đọc thường hữu ích hơn một báo cáo dài nhưng không có người sử dụng. Người tham gia được khuyến khích nói về điều đã giúp họ cảm thấy được chào đón, điều còn gây khó khăn và một thay đổi nhỏ có thể thử ngay trong tuần tới.</p>',
		'<ul><li>Ghi lại một quan sát cụ thể thay vì đánh giá chung chung.</li><li>Phân công người theo dõi việc tiếp nối sau sự kiện.</li><li>Ưu tiên giải pháp có thể duy trì bằng nguồn lực sẵn có.</li><li>Xin phản hồi từ cả người tổ chức lẫn người thụ hưởng.</li></ul>',
		'<p>Về mặt trình bày, bài viết kết hợp tiêu đề phụ, trích dẫn, danh sách và hình ảnh do Media Library quản lý. Ảnh có thông tin tác giả, giấy phép, mô tả và văn bản thay thế; quan hệ featured, inline hoặc gallery đều có thể chỉnh sửa trong WordPress. Điều này giúp cùng một fixture kiểm tra được trang danh sách, trang chi tiết, tìm kiếm, bài liên quan và trải nghiệm biên tập.</p>',
		'<p>Khi khép lại, câu chuyện không đưa ra một kết luận tuyệt đối. Nó mời người đọc chọn một việc vừa sức, quan sát tác động và quay lại điều chỉnh. Cách tiếp cận ấy phù hợp với tinh thần học hỏi liên tục: tôn trọng truyền thống, lắng nghe hoàn cảnh hiện tại và đo giá trị bằng những thay đổi có thể nhìn thấy trong đời sống thường ngày.</p>',
		...magazineExtension,
		'<p class="article-author">Ban biên tập dữ liệu preview PGDS</p>',
	].filter( Boolean ).join( '' );
}

function buildVideoBody( title, index ) {
	return [
		`<p><strong>${ title }</strong> là fixture video thứ ${ index + 1 } của môi trường preview. Clip là thành phần chính; phần chữ chỉ cung cấp bối cảnh cần thiết để người xem hiểu chủ đề, nhân vật và thời điểm ghi hình.</p>`,
		'<h2>Nội dung chính của video</h2>',
		'<p>Phóng sự đi theo diễn biến bằng hình ảnh, âm thanh hiện trường và các khoảnh khắc quan sát ngắn. Poster được quản lý trong Media Library, còn YouTube ID, tiêu đề đồng bộ và thời lượng được lưu ở các trường chuyên biệt để kiểm tra đầy đủ luồng biên tập Video.</p>',
		'<p>Người biên tập có thể thay sapo hoặc poster mà không phải viết lại một bài dài. Nội dung mô tả này cố ý gọn, giúp việc review tập trung vào trình phát, trạng thái video, khả năng hiển thị trên danh sách và trải nghiệm xem trên màn hình nhỏ.</p>',
		'<p class="article-author">Ban video PGDS</p>',
	].join( '' );
}

function buildEnglishBody( title, definition, index, includeInline ) {
	const marker = includeInline ? '<!--pgds-preview-inline-image-->' : '';
	return [
		`<p><strong>${ title }</strong> is a synthetic editorial feature created for the PGDS development environment. It presents a complete, reviewable story about ${ definition.focus }. Places, participants and measurements are illustrative, allowing the fixture to test realistic publishing workflows without attributing statements to real people or institutions.</p>`,
		'<p>The report begins with observable details: how a space is prepared, how responsibilities are shared, how newcomers are welcomed and how the team records what should improve. Focusing on process makes the story useful beyond a single event. It also gives editors enough varied material to assess typography, cards, excerpts, image handling, search results and related-content behavior.</p>',
		'<h2>Starting with community needs</h2>',
		`<p>This is entry ${ index + 1 } in the Vietnam Buddhism preview collection. The fictional editorial team first asks participants what would make the activity accessible and meaningful. Older visitors mention clear directions and places to rest. Younger volunteers ask for genuine responsibility. Families want a schedule they can understand before arriving. Bringing these perspectives together produces a plan that can be reviewed rather than assumed.</p>`,
		'<p>A short working document tracks ownership, timing, available resources and follow-up. This does not turn compassionate service into bureaucracy. It protects the work from depending on one person’s memory and helps support reach the people it was designed for. Unfinished tasks remain visible so the next gathering begins with evidence instead of starting over.</p>',
		marker,
		'<blockquote><p>A living tradition becomes credible when care is expressed through small actions that continue after the public moment has ended.</p></blockquote>',
		'<h2>Turning experience into daily practice</h2>',
		'<p>The second part considers how lessons become habits. A brief reflection after an activity, several open questions and a readable summary often provide more value than a long report no one revisits. Participants describe what helped them feel included, what created friction and one modest change that could be tested during the following week.</p>',
		'<ul><li>Record one specific observation instead of a broad judgement.</li><li>Name the person responsible for the next step.</li><li>Choose an approach that existing resources can sustain.</li><li>Invite feedback from organizers and participants alike.</li></ul>',
		'<p>The article combines headings, a pull quotation, a list and imagery managed in the WordPress Media Library. Every image carries source, author, licence, description and alternative text. Featured, inline, gallery and video-poster relationships remain editable through native CMS controls, so the same fixture can exercise archive pages, details, search, related stories and editorial changes.</p>',
		'<h2>Reading heritage in its local context</h2>',
		'<p>Vietnam Buddhism stories distinguish direct observation from community memory and from facts that require documentary confirmation. Architectural materials, changing patterns of use, seasonal rituals and the work of volunteers are described together. This gives readers a sense of how heritage remains active while giving editors enough specificity to review the English-only publication flow.</p>',
		'<p>The visual selection follows the same principle. A wide view establishes place, a closer frame identifies an activity and an attributed caption preserves context. The English surface uses separate Media Library records with English alternative text and descriptions, even when a licensed source image is shared with the Vietnamese fixtures.</p>',
		'<p>The report closes without claiming a universal answer. Readers are invited to choose a manageable action, observe its effect and revise the approach. That rhythm reflects an important quality of contemporary Buddhist community work in Vietnam: respect for inherited practice, attention to present conditions and a willingness to learn through responsible participation.</p>',
		'<p class="article-author">PGDS preview editorial team</p>',
	].filter( Boolean ).join( '' );
}

const articles = [];
const featured = [];
const inlineImages = [];
const galleries = [];
const videoPosters = [];
let globalIndex = 0;

for ( const [ categoryIndex, definition ] of categories.entries() ) {
	for ( let index = 0; index < 20; index++ ) {
		const sourceId = `preview-2026-${ definition.slug }-${ String( index + 1 ).padStart( 2, '0' ) }`;
		const subject = definition.subjects[ index % definition.subjects.length ];
		const angle = definition.language === 'en' ? englishAngles[ index ] : vietnameseAngles[ index ];
		const title = `${ subject }: ${ angle }`;
		const includeInline = definition.slug !== 'video' && ( definition.slug === 'emagazine' || index % 5 === 0 );
		const cats = definition.parent ? [ definition.parent, definition.slug ] : [ definition.slug ];
		const surfacePhotoIds = definition.language === 'en' ? englishPhotoAssetIds : photoAssetIds;
		const photoId = surfacePhotoIds[ ( globalIndex + categoryIndex * 3 ) % surfacePhotoIds.length ];
		const inlinePhotoId = surfacePhotoIds[ ( globalIndex + 7 ) % surfacePhotoIds.length ];
		const publishedDate = new Date( Date.UTC( 2026, 8, 7, 2, 0, 0 ) - globalIndex * 6 * 60 * 60 * 1000 )
			.toISOString()
			.slice( 0, 19 )
			.replace( 'T', ' ' );
		const video = definition.slug === 'video' ? videoCatalog[ index % videoCatalog.length ] : null;

		articles.push( {
			source_id: sourceId,
			title,
			slug: `${ slugify( subject ) }-${ definition.slug }-${ String( index + 1 ).padStart( 2, '0' ) }-preview`,
			sapo: definition.language === 'en'
				? `A development-only report on ${ definition.focus }, prepared with structured text and CMS-managed media for editorial review.`
				: `Bài viết mô phỏng về ${ definition.focus }, có cấu trúc nội dung và media do CMS quản lý để phục vụ kiểm thử biên tập.`,
			body_html: definition.slug === 'video'
				? buildVideoBody( title, index )
				: definition.language === 'en'
					? buildEnglishBody( title, definition, index, includeInline )
					: buildVietnameseBody( title, definition, index, includeInline ),
			primary_cat: definition.slug,
			cats,
			tags: definition.language === 'en'
				? [ definition.label, 'preview dataset', 'community practice' ]
				: [ definition.label, 'dữ liệu preview', 'đời sống Phật giáo' ],
			author: 'admin',
			published_at: publishedDate,
			featured_image_url: '',
			gallery: [],
			youtube_url: video?.id ?? '',
			source: definition.language === 'en' ? 'PGDS preview dataset' : 'Bộ dữ liệu preview PGDS',
			old_url: '',
		} );

		featured.push( { source_id: sourceId, asset_id: video?.poster ?? photoId } );
		if ( includeInline ) {
			inlineImages.push( { source_id: sourceId, asset_id: inlinePhotoId, size: 'large' } );
		}
		if ( definition.slug === 'emagazine' ) {
			galleries.push( {
				source_id: sourceId,
				asset_ids: [
					photoAssetIds[ ( globalIndex + 11 ) % photoAssetIds.length ],
					photoAssetIds[ ( globalIndex + 15 ) % photoAssetIds.length ],
				],
				columns: 2,
				size: 'large',
			} );
		}
		if ( video ) {
			videoPosters.push( { source_id: sourceId, asset_id: video.poster } );
		}

		globalIndex++;
	}
}

mediaManifest.schema_version = 2;
mediaManifest.notice = 'Development-only preview media for the 180-article editorial-surface dataset. Never deploy these fixtures as newsroom media.';
mediaManifest.assignments = {
	featured,
	inline_images: inlineImages,
	galleries,
	video_posters: videoPosters,
};

fs.writeFileSync( contentPath, `${ JSON.stringify( articles, null, 2 ) }\n` );
fs.writeFileSync( mediaPath, `${ JSON.stringify( mediaManifest, null, 2 ) }\n` );

process.stdout.write(
	`Generated ${ articles.length } articles, ${ featured.length } featured images, ` +
	`${ inlineImages.length } inline images, ${ galleries.length } galleries and ` +
	`${ videoPosters.length } video posters.\n`
);
